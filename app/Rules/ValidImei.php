<?php

namespace App\Rules;

use App\Support\Imei;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 15 digits, passing the Luhn checksum — see docs/design/01-domain-design.md. */
class ValidImei implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Imei::isValid($value)) {
            $fail('The :attribute must be a valid 15-digit IMEI.');
        }
    }
}
