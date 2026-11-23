<?php

namespace App\Http\Resources\Download;

use App\Dtos\Download\DownloadFileDetailsData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DownloadFileDetailsData */
class DownloadFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'index' => $this->index,
            'path' => $this->path,
            'size_bytes' => $this->sizeBytes,
            'progress' => $this->progress,
            'episode_id' => $this->episodeId,
            'show_id' => $this->showId,
        ];
    }
}
