<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DevicePart\UpdateIssuePartMapRequest;
use App\Models\DevicePart;
use App\Services\DiagnosisVisualService;
use App\Support\Diagnosis\IssueSource;
use Illuminate\Http\JsonResponse;

/**
 * The diagnosis taxonomy, with the parts each issue implicates.
 *
 * Deliberately not a table of its own: the shop already keeps two controlled
 * vocabularies — the intake problem tags and the finding defects — and a third
 * would just be a third answer to the same question. This endpoint projects
 * both, each entry carrying its mapped part keys so the picker can highlight
 * without a second round trip while a customer is watching.
 */
class IssueTypeController extends Controller
{
    public function __construct(private readonly DiagnosisVisualService $diagnosis) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', DevicePart::class);

        return response()->json(['data' => $this->diagnosis->issueTypes()]);
    }

    /**
     * Replace the part list for one issue key. Wholesale — see
     * DiagnosisVisualService::setMapping for why it is not a diff.
     */
    public function update(UpdateIssuePartMapRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->diagnosis->setMapping(
            IssueSource::from($validated['issue_source']),
            $validated['issue_key'],
            $validated['parts'],
        );

        return response()->json(['data' => $this->diagnosis->issueTypes()]);
    }
}
