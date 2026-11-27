<?php

namespace App\Services\Vlc\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Adds a file to the end of the playlist and plays it. The start time is an option of that one item, so the items after it
 * still play from their beginning.
 */
final class PlayRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $path,
        private readonly int $startSeconds,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/requests/status.json';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['command' => 'in_play', 'input' => $this->path, 'option' => "start-time={$this->startSeconds}"];
    }
}
