<?php

namespace App\Rules;

use App\Enums\ValidationErrorCode;
use Closure;

class AbsolutePath extends CodedRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!str_starts_with((string) $value, '/')) {
            $this->failWith($fail, ValidationErrorCode::AbsolutePath);
        }
    }
}
