<?php

namespace App\Services\QBittorrent\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Data\MultipartValue;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasMultipartBody;

/**
 * Adds a torrent by link. qBittorrent only takes this one as multipart form data. Answers "Ok.", or "Fails." when it
 * turned the torrent down.
 */
final class AddTorrentRequest extends Request implements HasBody
{
    use HasMultipartBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(private readonly array $fields) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/add';
    }

    /**
     * @return list<MultipartValue>
     */
    protected function defaultBody(): array
    {
        return array_map(
            fn (string $name, string $value): MultipartValue => new MultipartValue($name, $value),
            array_keys($this->fields),
            $this->fields,
        );
    }
}
