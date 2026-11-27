<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Sets the priority of some of a torrent's files: 0 skips them, 1 downloads them.
 */
final class SetFilePriorityRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    /**
     * @param  list<int>  $fileIndexes
     */
    public function __construct(
        private readonly string $hash,
        private readonly array $fileIndexes,
        private readonly int $priority,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/filePrio';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'hash' => $this->hash,
            'id' => implode('|', $this->fileIndexes),
            'priority' => (string) $this->priority,
        ];
    }
}
