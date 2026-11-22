<?php

namespace App\Http\Resources\Validation;

use App\Enums\ValidationErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use App\Rules\CodedRule;
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
     * Laravel's rules are known by name; our own rules and hand-added errors carry their code in the message. The
     * top-level code and params repeat the first error, so a caller showing a single message needn't look further.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $failedRules = $this->resource->validator->failed();
        $errors = [];

        foreach ($this->resource->errors() as $field => $messages) {
            $fieldErrors = [
                ...$this->fromLaravelRules($failedRules[$field] ?? []),
                ...array_filter(array_map($this->fromMessage(...), $messages)),
            ];

            // Plain text added by hand has neither a rule nor a code
            $errors[$field] = $fieldErrors ?: [$this->error(ValidationErrorCode::Invalid->value)];
        }

        return [...Arr::first(Arr::first($errors)), 'errors' => $errors];
    }

    /**
     * The validator names each failed rule: `Max` for a built-in one, the class name for a rule object such as
     * `Rule::enum`. Our own rules are skipped here: their message carries the code.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return list<array{code: string, params: object}>
     */
    private function fromLaravelRules(array $rules): array
    {
        $rules = array_filter($rules, fn (string $rule): bool => !is_subclass_of($rule, CodedRule::class), ARRAY_FILTER_USE_KEY);

        return array_values(array_map(function (string $rule, array $parameters): array {
            $code = ValidationErrorCode::tryFrom(Str::snake(class_basename($rule))) ?? ValidationErrorCode::Invalid;

            // Only the limits are worth showing; other rules' parameters are internals such as table names
            $hasLimit = in_array($code, [ValidationErrorCode::Min, ValidationErrorCode::Max], true);

            return $this->error($code->value, $hasLimit ? ['value' => (string) $parameters[0]] : []);
        }, array_keys($rules), $rules));
    }

    /**
     * A CodedRule's message is its code and params as JSON; a hand-added one (`withMessages`, an `after` hook) is a
     * bare ValidationErrorCode value. Anything else is Laravel's own text, already covered by its rule.
     *
     * @return array{code: string, params: object}|null
     */
    private function fromMessage(string $message): ?array
    {
        $coded = json_decode($message, true);

        if (is_array($coded) && isset($coded['code'])) {
            return $this->error($coded['code'], $coded['params'] ?? []);
        }

        return ValidationErrorCode::tryFrom($message) ? $this->error($message) : null;
    }

    /**
     * @param  array<string, string>  $params
     * @return array{code: string, params: object}
     */
    private function error(string $code, array $params = []): array
    {
        return ApiErrorDetail::make($code, $params);
    }
}
