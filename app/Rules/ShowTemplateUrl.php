<?php

namespace App\Rules;

use App\Actions\ShowTemplate\ResolveShowTemplateSourceAction;
use App\Enums\ValidationErrorCode;
use Closure;

/**
 * A link one of the show template sources can read, such as a nekoBT show page.
 */
class ShowTemplateUrl extends CodedRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (ResolveShowTemplateSourceAction::run((string) $value) === null) {
            $this->failWith($fail, ValidationErrorCode::UnsupportedShowTemplateUrl);
        }
    }
}
