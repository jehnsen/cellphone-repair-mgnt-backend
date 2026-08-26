<?php

namespace App\Support;

class Imei
{
    public static function normalize(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }

    public static function isValid(string $raw): bool
    {
        $digits = self::normalize($raw);

        if (strlen($digits) !== 15) {
            return false;
        }

        return self::passesLuhn($digits);
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $alternate = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];

            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }

            $sum += $n;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
