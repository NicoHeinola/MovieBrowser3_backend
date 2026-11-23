<?php

namespace App\Services;

use JsonException;
use Saloon\Http\Response;

/**
 * The answer of an outside system, read like Laravel's HTTP client reads one: a body that isn't JSON, such as a bot
 * check's HTML page, has no JSON values instead of throwing. Every connector answers with it, so services check an
 * answer's shape rather than catch parse errors.
 */
class LenientJsonResponse extends Response
{
    public function json(string|int|null $key = null, mixed $default = null): mixed
    {
        try {
            return parent::json($key, $default);
        } catch (JsonException) {
            return $key === null ? [] : $default;
        }
    }
}
