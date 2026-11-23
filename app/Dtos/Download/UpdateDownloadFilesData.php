<?php

namespace App\Dtos\Download;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class UpdateDownloadFilesData extends Data
{
    /**
     * Every file of the download picked for an episode. Files left out are not downloaded.
     *
     * @param  int  $showId  The show the episodes belong to
     * @param  list<DownloadPickData>  $files
     */
    public function __construct(
        public int $showId,
        #[DataCollectionOf(DownloadPickData::class)]
        public array $files,
    ) {}
}
