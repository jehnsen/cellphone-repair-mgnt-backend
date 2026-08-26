<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalizes any common PH mobile input (09171234567, 9171234567,
     * +639171234567, 639171234567, with spaces/dashes) to +63XXXXXXXXXX —
     * exactly 13 characters, matching the `mobile` column.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return '+63'.$digits;
    }
}
