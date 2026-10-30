<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AbsolutePath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = (string) $value;

        if (!str_starts_with($path, '/') && preg_match('~^[A-Za-z]:[\\\\/]~', $path) !== 1) {
            $fail('The :attribute must be an absolute path.');
        }
    }
}
