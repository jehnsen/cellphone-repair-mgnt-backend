<?php

namespace App\Support;

/**
 * The VAT/discount math behind a sale — pulled out of SaleService so it's
 * unit-testable without a database. Mirrors the VAT formula
 * `DatabaseSeeders\ShiftAndSalesSeeder` already uses for demo data
 * (`vat = subtotal / 1.12 * 0.12`), generalized to handle discounts, which
 * the seeder didn't need.
 *
 * Simplifying decisions the design brief doesn't nail down (flagging, same
 * spirit as docs/design/01-domain-design.md §7):
 *   - At most one discount per line (percent/amount only — a promo-style
 *     markdown), plus at most one discount for the whole sale.
 *   - senior_citizen/pwd is sale-scope only, never per-line — realistically
 *     one ID card covers the whole transaction, and legally it's the total
 *     purchase from that customer that's exempt, not individual items.
 *   - zero_rated_sales is always 0 — nothing this shop sells qualifies for
 *     zero-rating (that's for exporters/specific goods classes).
 *   - A non-VAT-registered branch still fills `vatable_sales` (equal to the
 *     gross, since there's no VAT to strip out) but `vat_amount` is always 0.
 */
class SaleCalculator
{
    public const VAT_RATE = 0.12;

    public const SENIOR_PWD_RATE = 0.20;

    /**
     * @param  list<array{amount: float, discount: ?array{type: string, value: float}}>  $lines  quantity*unit_price per line (VAT-inclusive gross) and its optional line-scope discount
     * @param  ?array{type: string, value: float}  $saleDiscount  an additional discount applied to the post-line-discount vatable total
     * @return array{subtotal: float, discount_total: float, vatable_sales: float, vat_exempt_sales: float, zero_rated_sales: float, vat_amount: float, total: float}
     */
    public static function compute(array $lines, ?array $saleDiscount, bool $vatRegistered): array
    {
        $subtotal = 0.0;
        $vatableGross = 0.0;

        foreach ($lines as $line) {
            $gross = round($line['amount'], 2);
            $subtotal += $gross;
            $vatableGross += self::applyPercentOrAmount($gross, $line['discount']);
        }

        $vatExemptSales = 0.0;

        if ($saleDiscount !== null) {
            if (in_array($saleDiscount['type'], ['senior_citizen', 'pwd'], true)) {
                $exemptNet = $vatableGross / (1 + self::VAT_RATE);
                $vatExemptSales = round($exemptNet * (1 - self::SENIOR_PWD_RATE), 2);
                $vatableGross = 0.0;
            } else {
                $vatableGross = self::applyPercentOrAmount($vatableGross, $saleDiscount);
            }
        }

        $vatAmount = $vatRegistered ? round($vatableGross - ($vatableGross / (1 + self::VAT_RATE)), 2) : 0.0;
        $vatableSales = round($vatableGross - $vatAmount, 2);
        $total = round($vatableGross + $vatExemptSales, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($subtotal - $total, 2),
            'vatable_sales' => $vatableSales,
            'vat_exempt_sales' => $vatExemptSales,
            'zero_rated_sales' => 0.0,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ];
    }

    /**
     * Public so SaleService can compute the same net amount for the
     * `sale_lines.amount`/`line_discount` columns without duplicating (or
     * drifting from) this formula.
     *
     * @param  ?array{type: string, value: float}  $discount
     */
    public static function applyPercentOrAmount(float $gross, ?array $discount): float
    {
        if ($discount === null) {
            return $gross;
        }

        $off = $discount['type'] === 'percent' ? $gross * ($discount['value'] / 100) : $discount['value'];

        return max(0.0, $gross - $off);
    }
}
