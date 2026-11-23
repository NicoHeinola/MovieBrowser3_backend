<?php

namespace App\Services\Mal\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The search box's suggestions: anime whose names start like the keyword, about ten.
 */
final class SearchAnimeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $keyword) {}

    public function resolveEndpoint(): string
    {
        return '/search/prefix.json';
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultQuery(): array
    {
        return ['type' => 'anime', 'keyword' => $this->keyword, 'v' => 1];
    }
}
