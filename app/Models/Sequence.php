<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

/**
 * Gapless per-branch, per-year (or per-year-per-month) document number
 * series. Never MAX(id)+1 — locked with SELECT ... FOR UPDATE inside the
 * caller's transaction.
 */
#[Fillable(['branch_id', 'scope', 'year', 'month', 'last_number'])]
class Sequence extends Model
{
    public $timestamps = false;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function next(int $branchId, string $scope, int $year, ?int $month = null): int
    {
        $row = self::query()
            ->where('branch_id', $branchId)
            ->where('scope', $scope)
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            try {
                $row = self::create([
                    'branch_id' => $branchId,
                    'scope' => $scope,
                    'year' => $year,
                    'month' => $month,
                    'last_number' => 0,
                ]);
            } catch (QueryException) {
                // Lost the race to create this row — another transaction
                // just committed it; re-select under the lock and continue.
                $row = self::query()
                    ->where('branch_id', $branchId)
                    ->where('scope', $scope)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $row->increment('last_number');

        return $row->last_number;
    }
}
