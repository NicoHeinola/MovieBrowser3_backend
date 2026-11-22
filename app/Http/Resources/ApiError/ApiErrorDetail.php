<?php

namespace App\Http\Resources\ApiError;

use BackedEnum;
use Illuminate\Http\JsonResponse;

/**
 * The `{ code, params }` shape every API error takes: error responses, validation errors and the `error` field of a
 * background run's status. The frontend translates the code and fills its placeholders from the params.
 */
final class ApiErrorDetail
{
    /**
     * @param  array<string, string>  $params
     * @return array{code: string, params: object}
     */
    public static function make(BackedEnum|string $code, array $params = []): array
    {
        return [
            'code' => $code instanceof BackedEnum ? (string) $code->value : $code,
            // An empty PHP array would encode as [], and the contract is always an object
            'params' => (object) $params,
        ];
    }

    /**
     * An error response in that shape, for exceptions and middleware.
     *
     * @param  array<string, string>  $params
     */
    public static function response(BackedEnum|string $code, int $status, array $params = []): JsonResponse
    {
        return response()->json(self::make($code, $params), $status);
    }
}
