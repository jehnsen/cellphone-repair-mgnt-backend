<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Models\TicketPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Binary never travels through a controller (Rule Zero): the upload is
 * multipart in, but every response — including this one — is JSON
 * containing a ULID and a short-TTL signed URL. Downloads happen against
 * that signed URL, served directly by the storage layer, outside the
 * controller entirely.
 */
class TicketPhotoService
{
    private const SIGNED_URL_MINUTES = 15;

    public function __construct(private readonly TicketEventRecorder $events) {}

    public function list(RepairTicket $ticket)
    {
        // capturedBy (User) is branch-scoped; the photographer may not share
        // the viewer's branch (see RepairTicketService::loadDisplayRelations).
        return $ticket->photos()
            ->with(['capturedBy' => fn ($query) => $query->withoutGlobalScopes()])
            ->latest('captured_at')
            ->get();
    }

    public function upload(RepairTicket $ticket, UploadedFile $file, string $phase, User $actor): TicketPhoto
    {
        $path = $file->storeAs(
            'ticket-photos/'.$ticket->ulid,
            (string) Str::ulid().'.'.$file->extension(),
            'local',
        );

        $photo = TicketPhoto::create([
            'repair_ticket_id' => $ticket->id,
            'phase' => $phase,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'sha256_hash' => hash_file('sha256', $file->getRealPath()),
            'captured_at' => now(),
            'captured_by' => $actor->id,
        ]);

        $this->events->record(
            $ticket,
            'photo_added',
            $actor,
            metadata: ['photo_id' => $photo->id, 'phase' => $phase],
        );

        return $photo->setRelation('capturedBy', $actor);
    }

    public function signedUrl(TicketPhoto $photo): string
    {
        return Storage::disk($photo->storage_disk)
            ->temporaryUrl($photo->storage_path, now()->addMinutes(self::SIGNED_URL_MINUTES));
    }
}
