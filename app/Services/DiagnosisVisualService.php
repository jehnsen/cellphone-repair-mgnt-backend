<?php

namespace App\Services;

use App\Models\DevicePart;
use App\Models\IssuePartLink;
use App\Models\RepairTicket;
use App\Models\TicketDiagnosisSnapshot;
use App\Models\User;
use App\Support\Diagnosis\IssueSource;
use App\Support\Diagnosis\ProblemTag;
use App\Support\RepairFinding\Defect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything behind the diagnosis visualizer: the parts of the generic rig,
 * the issue-to-part mapping over the two vocabularies the shop already keeps,
 * and the frozen snapshots.
 *
 * There is no diagnosis record in here on purpose. The diagnosis *is* the
 * ticket's repair finding (and, before one exists, the intake problem tags);
 * this service only turns those into parts to draw. Confirming a diagnosis in
 * the visualizer goes through RepairFindingService like any other edit, so
 * there stays exactly one place the shop's answer to "what was wrong" is
 * written.
 */
class DiagnosisVisualService
{
    private const SIGNED_URL_MINUTES = 15;

    /**
     * The rig, front of the device first.
     *
     * @return Collection<int, DevicePart>
     */
    public function parts(bool $includeInactive = false): Collection
    {
        return DevicePart::query()
            ->unless($includeInactive, fn ($query) => $query->active())
            ->inAssemblyOrder()
            ->get();
    }

    /**
     * The issue taxonomy the picker lists, each entry carrying the part keys
     * it implicates.
     *
     * Both vocabularies come back in one call because the picker shows both:
     * a ticket that has been to the bench is diagnosed by defect, one that
     * has not is still described by the tags taken at the counter.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function issueTypes(): array
    {
        $mapped = $this->mapping();

        return [
            IssueSource::ProblemTag->value => array_map(
                fn (ProblemTag $tag) => [
                    'source' => IssueSource::ProblemTag->value,
                    'key' => $tag->value,
                    'label' => $tag->label(),
                    'part_keys' => $mapped[IssueSource::ProblemTag->value][$tag->value] ?? [],
                ],
                ProblemTag::cases(),
            ),
            IssueSource::Defect->value => array_map(
                fn (Defect $defect) => [
                    'source' => IssueSource::Defect->value,
                    'key' => $defect->value,
                    'label' => $defect->label(),
                    'part_keys' => $mapped[IssueSource::Defect->value][$defect->value] ?? [],
                ],
                Defect::cases(),
            ),
        ];
    }

    /**
     * The whole mapping, as source then issue key then ordered part keys.
     *
     * One query for the lot rather than one per issue: the picker needs all of
     * it up front so selecting an issue highlights instantly, with no round
     * trip while a customer is watching.
     *
     * @return array<string, array<string, list<string>>>
     */
    public function mapping(): array
    {
        $links = IssuePartLink::query()
            ->join('device_parts', 'device_parts.id', '=', 'issue_part_map.device_part_id')
            ->where('device_parts.is_active', true)
            ->orderBy('issue_part_map.rank')
            ->orderBy('device_parts.sort_order')
            ->get([
                'issue_part_map.issue_source',
                'issue_part_map.issue_key',
                'device_parts.key as part_key',
            ]);

        $mapped = [];

        foreach ($links as $link) {
            $source = $link->issue_source instanceof IssueSource
                ? $link->issue_source->value
                : (string) $link->issue_source;

            $mapped[$source][$link->issue_key][] = $link->part_key;
        }

        return $mapped;
    }

    /**
     * The part keys a set of issue keys implicates, de-duplicated but keeping
     * the order the mapping gives — rank 1 first, because the camera frames
     * the leading part and the summary panel names it first.
     *
     * @param  list<string>  $issueKeys
     * @return list<string>
     */
    public function partKeysFor(IssueSource $source, array $issueKeys): array
    {
        $mapped = $this->mapping()[$source->value] ?? [];
        $keys = [];

        foreach ($issueKeys as $issueKey) {
            foreach ($mapped[$issueKey] ?? [] as $partKey) {
                if (! in_array($partKey, $keys, true)) {
                    $keys[] = $partKey;
                }
            }
        }

        return $keys;
    }

    /**
     * Replace the parts one issue key maps to. Wholesale rather than
     * incremental: the admin UI edits an issue's whole part list at once, and
     * a diff would leave a half-applied mapping if it failed midway.
     *
     * @param  list<array{part_ulid: string, rank?: int}>  $parts
     */
    public function setMapping(IssueSource $source, string $issueKey, array $parts): void
    {
        DB::transaction(function () use ($source, $issueKey, $parts): void {
            IssuePartLink::query()->forIssue($source, $issueKey)->delete();

            foreach ($parts as $index => $part) {
                IssuePartLink::create([
                    'issue_source' => $source->value,
                    'issue_key' => $issueKey,
                    'device_part_id' => DevicePart::idFromUlid($part['part_ulid']),
                    'rank' => $part['rank'] ?? $index + 1,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, TicketDiagnosisSnapshot>
     */
    public function snapshots(RepairTicket $ticket): Collection
    {
        // capturedBy is branch-scoped and the technician who captured this may
        // not share the viewer's branch — the same reason TicketPhotoService
        // drops the scope here.
        return $ticket->diagnosisSnapshots()
            ->with(['capturedBy' => fn ($query) => $query->withoutGlobalScopes()])
            ->latest('created_at')
            ->get();
    }

    /**
     * Freeze what the customer was shown.
     *
     * Multipart in, JSON out, exactly like a ticket photo — the rendered
     * canvas is a PNG by the time it reaches here, and binary never travels
     * through a controller in either direction (Rule Zero).
     *
     * @param  array{issue_source: string, issue_keys: list<string>, part_keys: list<string>, camera?: array<string, mixed>|null, note?: string|null}  $state
     */
    public function storeSnapshot(
        RepairTicket $ticket,
        UploadedFile $image,
        array $state,
        User $actor,
    ): TicketDiagnosisSnapshot {
        $path = $image->storeAs(
            'ticket-diagnosis-snapshots/'.$ticket->ulid,
            (string) Str::ulid().'.'.$image->extension(),
            'local',
        );

        $snapshot = TicketDiagnosisSnapshot::create([
            'repair_ticket_id' => $ticket->id,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'sha256_hash' => hash_file('sha256', $image->getRealPath()),
            'issue_source' => $state['issue_source'],
            'issue_keys' => array_values($state['issue_keys']),
            'part_keys' => array_values($state['part_keys']),
            'camera' => $state['camera'] ?? null,
            'note' => $state['note'] ?? null,
            'captured_by' => $actor->id,
        ]);

        return $snapshot->setRelation('capturedBy', $actor);
    }

    public function signedUrl(TicketDiagnosisSnapshot $snapshot): string
    {
        return Storage::disk($snapshot->storage_disk)
            ->temporaryUrl($snapshot->storage_path, now()->addMinutes(self::SIGNED_URL_MINUTES));
    }
}
