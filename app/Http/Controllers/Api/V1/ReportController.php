<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** All read-only, JSON with `data.aggregate`/`data.rows` and `meta.generated_at` per the design brief. */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function sales(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->dateRange($request);

        return $this->respond($this->reports->sales($from, $to));
    }

    public function margin(Request $request): JsonResponse
    {
        $this->authorizeReports($request, requireMargin: true);
        [$from, $to] = $this->dateRange($request);

        return $this->respond($this->reports->margin($from, $to));
    }

    public function technicianThroughput(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->dateRange($request);

        return $this->respond($this->reports->technicianThroughput($from, $to));
    }

    public function mostRepairedModels(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->dateRange($request);

        return $this->respond($this->reports->mostRepairedModels($from, $to));
    }

    public function warrantyFailureRate(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return $this->respond($this->reports->warrantyFailureRate());
    }

    public function inventoryValuation(Request $request): JsonResponse
    {
        $this->authorizeReports($request, requireMargin: true);

        return $this->respond($this->reports->inventoryValuation());
    }

    public function deadStock(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365']]);

        return $this->respond($this->reports->deadStock((int) $request->integer('days', 60)));
    }

    public function unclaimedAging(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return $this->respond($this->reports->unclaimedAging());
    }

    public function commissionsPayable(Request $request): JsonResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->dateRange($request);

        return $this->respond($this->reports->commissionsPayable($from, $to));
    }

    private function authorizeReports(Request $request, bool $requireMargin = false): void
    {
        abort_unless((bool) $request->user()?->can('reports.view'), 403, 'You do not have permission to view reports.');

        if ($requireMargin) {
            abort_unless($request->user()->can('reports.margin.view'), 403, 'This report requires margin visibility.');
        }
    }

    /** @return array{0: ?string, 1: ?string} */
    private function dateRange(Request $request): array
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return [$request->query('date_from'), $request->query('date_to')];
    }

    private function respond(array $data): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => ['generated_at' => now()->toIso8601String()]]);
    }
}
