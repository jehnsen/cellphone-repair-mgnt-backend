<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** PH Tax Identification Number: 9-15 digits, dashes/spaces allowed in input. */
class ValidTin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid TIN.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            $fail('The :attribute must be a valid TIN.');
        }
    }
}
