<?php

namespace App\Http\Resources\Episode;

use App\Models\DownloadPick\DownloadPick;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The download an episode waits for, and which of its files.
 *
 * @mixin DownloadPick
 */
class EpisodeDownloadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->download_id,
            'file_index' => $this->file_index,
            'path' => $this->path,
            'status' => $this->whenLoaded('download', fn () => $this->download->status),
            'imported_at' => $this->imported_at,
        ];
    }
}
