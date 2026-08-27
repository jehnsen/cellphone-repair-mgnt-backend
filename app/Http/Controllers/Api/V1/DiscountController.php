<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Discount\CalculateDiscountRequest;
use App\Models\Branch;
use App\Support\SaleCalculator;
use Illuminate\Http\JsonResponse;

class DiscountController extends Controller
{
    /**
     * A stateless preview — no sale is created. Lets the POS UI show the
     * VAT/discount breakdown before checkout commits to it. Uses the
     * authenticated cashier's own branch to decide vat_registered, same as
     * a real sale would.
     */
    public function calculate(CalculateDiscountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branch = Branch::findOrFail($request->user()->branch_id);

        $discount = ['type' => $data['type'], 'value' => (float) ($data['value'] ?? SaleCalculator::SENIOR_PWD_RATE * 100)];

        $totals = SaleCalculator::compute(
            [['amount' => (float) $data['amount'], 'discount' => null]],
            $discount,
            (bool) $branch->vat_registered,
        );

        // Formatted to match decimal:2-cast money fields everywhere else in
        // the API (a raw PHP float loses its trailing zero on encoding —
        // 100.0 becomes JSON `100`, not `100.00`).
        return response()->json(['data' => array_map(fn ($value) => number_format($value, 2, '.', ''), $totals)]);
    }
}
