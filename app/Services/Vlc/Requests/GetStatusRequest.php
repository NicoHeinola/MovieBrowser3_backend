<?php

namespace App\Services\Vlc\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * VLC's status: current file, state and position.
 */
final class GetStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/requests/status.json';
    }
}
