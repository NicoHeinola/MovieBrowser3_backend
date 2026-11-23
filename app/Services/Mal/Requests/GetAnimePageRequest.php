<?php

namespace App\Services\Mal\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * An anime's page as HTML, by its id.
 */
final class GetAnimePageRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $animeId) {}

    public function resolveEndpoint(): string
    {
        return "/anime/{$this->animeId}";
    }
}
