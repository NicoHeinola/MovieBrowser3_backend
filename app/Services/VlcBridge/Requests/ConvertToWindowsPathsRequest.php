<?php

namespace App\Services\VlcBridge\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Converts paths to the ones a Windows VLC opens: `paths`. Answers `{ paths }` in the same order.
 */
final class ConvertToWindowsPathsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function resolveEndpoint(): string
    {
        return '/windows-paths';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
