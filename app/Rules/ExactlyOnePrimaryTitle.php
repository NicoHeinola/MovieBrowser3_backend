<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExactlyOnePrimaryTitle implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $primaryTitleCount = collect($value)
            ->filter(static fn (mixed $title): bool => is_array($title) && ($title['is_primary'] ?? false) === true)
            ->count();

        if ($primaryTitleCount !== 1) {
            $fail('Exactly one primary title is required.');
        }
    }
}
