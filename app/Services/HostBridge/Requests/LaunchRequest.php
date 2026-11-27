<?php

namespace App\Services\HostBridge\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Starts VLC with its web interface.
 */
final class LaunchRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $binary,
        private readonly int $port,
        #[\SensitiveParameter]
        private readonly string $password,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/launch';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['binary' => $this->binary, 'port' => $this->port, 'password' => $this->password];
    }
}
