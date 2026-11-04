<?php

namespace App\Http\Resources\Validation;

use App\Enums\ValidationErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A validation failure as codes for the frontend to translate.
 *
 * @mixin ValidationException
 */
class ValidationErrorResource extends JsonResource
{
    /**
     * The top-level code and params repeat the first error, so a caller showing a single message doesn't have to look
     * through the fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $failedRules = $this->resource->validator->failed();
        $errors = [];

        foreach ($this->resource->errors() as $field => $messages) {
            $fieldErrors = [
                ...$this->fromFailedRules($failedRules[$field] ?? []),
                ...$this->fromMessages($messages),
            ];

            // Errors added by hand with plain text have neither a rule nor a code
            $errors[$field] = $fieldErrors ?: [$this->error(ValidationErrorCode::Invalid)];
        }

        return [...Arr::first(Arr::first($errors)), 'errors' => $errors];
    }

    /**
     * The validator names each failed rule: `Max` for a built-in one, the class name for a rule object.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return list<array{code: string, params: object}>
     */
    private function fromFailedRules(array $rules): array
    {
        return array_map(function (string $rule, array $parameters): array {
            $code = ValidationErrorCode::tryFrom(Str::snake(class_basename($rule))) ?? ValidationErrorCode::Invalid;

            // Only the limits are worth showing; other rules' parameters are internals such as table names
            $hasLimit = in_array($code, [ValidationErrorCode::Min, ValidationErrorCode::Max], true);

            return $this->error($code, $hasLimit ? ['value' => (string) $parameters[0]] : []);
        }, array_keys($rules), $rules);
    }

    /**
     * Errors raised outside a rule (ValidationException::withMessages, an `after` hook) use the code as their message.
     *
     * @param  list<string>  $messages
     * @return list<array{code: string, params: object}>
     */
    private function fromMessages(array $messages): array
    {
        $codes = array_filter(array_map(ValidationErrorCode::tryFrom(...), $messages));

        return array_values(array_map($this->error(...), $codes));
    }

    /**
     * @param  array<string, string>  $params
     * @return array{code: string, params: object}
     */
    private function error(ValidationErrorCode $code, array $params = []): array
    {
        return ['code' => $code->value, 'params' => (object) $params];
    }
}
