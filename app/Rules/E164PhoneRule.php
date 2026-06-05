<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class E164PhoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Validates that the phone number matches E.164 international format:
     * + followed by 1 to 15 digits, where the first digit is 1-9.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\+[1-9]\d{0,14}$/', $value)) {
            $fail('Phone number must be in international format (e.g., +60123456789).');
        }
    }
}
