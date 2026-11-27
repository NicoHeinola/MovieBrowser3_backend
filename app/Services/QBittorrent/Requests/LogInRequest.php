<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Logs in. The session cookie comes back in the headers.
 */
final class LogInRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/auth/login';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['username' => $this->username, 'password' => $this->password];
    }
}
