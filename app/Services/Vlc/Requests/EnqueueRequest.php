<?php

namespace App\Services\Vlc\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Adds a file to the end of the playlist.
 */
final class EnqueueRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $path) {}

    public function resolveEndpoint(): string
    {
        return '/requests/status.json';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['command' => 'in_enqueue', 'input' => $this->path];
    }
}
