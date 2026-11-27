<?php

namespace App\Services\HostBridge\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Checks that VLC can be started from a binary. 204 when it can, 422 with a code when not.
 */
final class CheckVlcCanStartRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $binary) {}

    public function resolveEndpoint(): string
    {
        return '/check-binary';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['binary' => $this->binary];
    }
}
