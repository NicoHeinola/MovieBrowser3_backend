<?php

namespace App\Actions\Download;

use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Exceptions\DownloadNotRetryableException;
use App\Models\Download\Download;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDownloadRetryAction
{
    use AsAction;

    /**
     * Sends a failed download back to downloading, so the next sync tries its imports again, such as after freeing disk
     * space. A torrent qBittorrent lost can't come back this way: it's added again instead.
     *
     * @throws DownloadNotRetryableException
     */
    public function handle(Download $download): Download
    {
        if ($download->status !== DownloadStatus::Failed || $download->error_code === DownloadErrorCode::ClientLostTorrent) {
            throw new DownloadNotRetryableException;
        }

        return DB::transaction(function () use ($download): Download {
            $download->update(['status' => DownloadStatus::Downloading, 'error_code' => null]);

            return $download->fresh();
        });
    }
}
