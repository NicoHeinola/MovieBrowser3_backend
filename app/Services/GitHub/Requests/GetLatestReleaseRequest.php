<?php

namespace App\Services\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * A repo's newest published release, by `owner/name`.
 */
final class GetLatestReleaseRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $repo) {}

    public function resolveEndpoint(): string
    {
        return "/repos/{$this->repo}/releases/latest";
    }
}
