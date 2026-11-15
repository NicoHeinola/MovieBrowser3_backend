<?php

namespace App\Rules;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Base of every custom rule: it fails with a code, plus params when the message needs them, never with text. The code
 * may come from another enum (a setup check code).
 */
abstract class CodedRule implements ValidationRule
{
    /**
     * @param  array<string, string>  $params
     */
    protected function failWith(Closure $fail, BackedEnum $code, array $params = []): void
    {
        // The message is only read by ValidationErrorResource, which turns it back into the code and params
        $fail(json_encode(['code' => $code->value, 'params' => $params], JSON_THROW_ON_ERROR));
    }
}
