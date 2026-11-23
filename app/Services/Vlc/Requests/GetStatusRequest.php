<?php

namespace App\Services\Vlc\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * VLC's status (current file, state, position). A command such as `['command' => 'in_enqueue', 'input' => $path]` runs
 * first.
 */
final class GetStatusRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, string>  $command
     */
    public function __construct(private readonly array $command = []) {}

    public function resolveEndpoint(): string
    {
        return '/requests/status.json';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return $this->command;
    }
}
