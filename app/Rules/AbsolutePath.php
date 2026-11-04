<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AbsolutePath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!str_starts_with((string) $value, '/')) {
            $fail('The :attribute must be an absolute path.');
        }
    }
}
