<?php

namespace App\Services\NekoBt\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The show or movie behind a media page: `s1453` for a series, `m134` for a movie.
 */
final class GetMediaRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $mediaId) {}

    public function resolveEndpoint(): string
    {
        return "/media/{$this->mediaId}";
    }
}
