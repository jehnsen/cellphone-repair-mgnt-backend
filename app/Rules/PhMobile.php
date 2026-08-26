<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Accepts common PH mobile input formats (09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX). */
class PhMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid Philippine mobile number.');

            return;
        }

        $normalized = PhoneNumber::normalize($value);

        if (! preg_match('/^\+639\d{9}$/', $normalized)) {
            $fail('The :attribute must be a valid Philippine mobile number.');
        }
    }
}
