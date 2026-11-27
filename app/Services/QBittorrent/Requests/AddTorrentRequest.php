<?php

namespace App\Services\QBittorrent\Requests;

use App\Dtos\QBittorrent\QBittorrentNewTorrentData;
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

    public function __construct(private readonly QBittorrentNewTorrentData $torrent) {}

    public function resolveEndpoint(): string
    {
        return '/torrents/add';
    }

    /**
     * @return list<MultipartValue>
     */
    protected function defaultBody(): array
    {
        $isMagnet = str_starts_with($this->torrent->url, 'magnet:');

        return [
            new MultipartValue('urls', $this->torrent->url),
            new MultipartValue('savepath', $this->torrent->savePath),
            new MultipartValue('category', $this->torrent->category),
            // Nothing downloads yet: a `.torrent` link is added stopped, and a magnet runs only until its file list is known
            new MultipartValue('stopped', $isMagnet ? 'false' : 'true'),
            new MultipartValue('stopCondition', $isMagnet ? 'MetadataReceived' : 'None'),
        ];
    }
}
