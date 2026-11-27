<?php

namespace App\Services\Mal;

use App\Services\ServiceConnector;

/**
 * MyAnimeList's website: its search box's JSON and its anime pages' HTML. Only `MalService` uses it.
 */
final class MalConnector extends ServiceConnector
{
    public function resolveBaseUrl(): string
    {
        return rtrim((string) config('mal.base_url'), '/');
    }

    public function getRequestTimeout(): float
    {
        return (float) config('mal.timeout_seconds');
    }
}
