<?php

namespace App\Services\HostBridge\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Copies a file. The bridge answers once the copy is done.
 */
final class CopyFileRequest extends Request implements HasBody
{
    use HasJsonBody;
    use HasTimeout;

    protected Method $method = Method::POST;

    protected int $connectTimeout = 10;

    // Hours, for a big file on a slow drive
    protected int $requestTimeout = 6 * 60 * 60;

    public function __construct(
        private readonly string $fromPath,
        private readonly string $toPath,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/copy-file';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['from' => $this->fromPath, 'to' => $this->toPath];
    }
}
