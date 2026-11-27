<?php

namespace App\Dtos\QBittorrent;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class QBittorrentTorrentData extends Data
{
    /** qBittorrent's time left when it can't tell: 100 days */
    private const int UNKNOWN_ETA = 8_640_000;

    /**
     * One torrent as qBittorrent reports it right now.
     *
     * @param  string  $state  qBittorrent's own state, such as `downloading` or `stoppedDL`
     * @param  float  $progress  0 to 1, over the files that download
     * @param  int  $sizeBytes  The size of the files that download
     * @param  int|null  $eta  Seconds until the download is complete; null when qBittorrent can't tell
     * @param  bool  $hasMetadata  False while a magnet's file list is still being fetched; older qBittorrents don't say
     */
    public function __construct(
        public string $hash,
        public string $name,
        public string $state,
        public float $progress,
        #[MapInputName('size')]
        public int $sizeBytes,
        #[MapInputName('downloaded')]
        public int $downloadedBytes = 0,
        #[MapInputName('dlspeed')]
        public int $downloadSpeed = 0,
        public ?int $eta = null,
        #[MapInputName('has_metadata')]
        public bool $hasMetadata = true,
    ) {}

    /**
     * Changes qBittorrent's "not known" time left (100 days or more) to null.
     *
     * @param  array<string, mixed>  $properties  qBittorrent's torrent, before the names are mapped
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (($properties['eta'] ?? 0) >= self::UNKNOWN_ETA) {
            $properties['eta'] = null;
        }

        return $properties;
    }

    /**
     * Whether qBittorrent is checking its files on the disk. Until it's done, it may still call missing files finished.
     */
    public function isChecking(): bool
    {
        return str_starts_with($this->state, 'checking');
    }

    /**
     * Whether it finished downloading and qBittorrent stopped it: done seeding, by its share limit or by hand. Older
     * qBittorrents call the state `pausedUP`.
     */
    public function isStoppedAfterFinishing(): bool
    {
        return in_array($this->state, ['stoppedUP', 'pausedUP'], true);
    }
}
