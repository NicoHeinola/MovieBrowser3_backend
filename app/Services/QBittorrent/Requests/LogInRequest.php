<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Logs in: `username` and `password`. Answers "Ok." with a session cookie, "Fails." for a wrong login.
 */
final class LogInRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(private readonly array $fields) {}

    public function resolveEndpoint(): string
    {
        return '/auth/login';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return $this->fields;
    }
}
