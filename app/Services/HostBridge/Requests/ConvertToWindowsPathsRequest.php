<?php

namespace App\Services\HostBridge\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Converts paths to the ones a Windows VLC opens. Answers `{ paths }` in the same order.
 */
final class ConvertToWindowsPathsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(private readonly array $paths) {}

    public function resolveEndpoint(): string
    {
        return '/windows-paths';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['paths' => $this->paths];
    }
}
