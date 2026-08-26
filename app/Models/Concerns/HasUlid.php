<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Internal keys stay BIGINT AUTO_INCREMENT; every externally exposed record
 * also gets a ULID and routes bind on that, never the sequential id (see
 * docs/design/01-domain-design.md, Rule 6).
 */
trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
