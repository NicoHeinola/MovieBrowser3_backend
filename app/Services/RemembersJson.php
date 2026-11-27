<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Request;

/**
 * For services whose system shouldn't be asked the same thing again and again. Keeps what it sent, not what the app made
 * of that, so an update that reads it differently needs no fresh answer.
 */
trait RemembersJson
{
    /**
     * @param  int  $seconds  How long the answer is kept
     * @param  Closure(): array<mixed>  $fetchJson  Sends the request; when it throws, nothing is kept
     * @return array<mixed>
     */
    private function rememberJson(Request $request, int $seconds, Closure $fetchJson): array
    {
        $query = json_encode($request->query()->all());
        // Hashed, because a cache key has a length limit
        $requestHash = hash('xxh128', $request->resolveEndpoint().$query);

        return Cache::remember('json.'.$request::class.".{$requestHash}", $seconds, $fetchJson);
    }
}
