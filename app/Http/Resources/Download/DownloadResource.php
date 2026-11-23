<?php

namespace App\Http\Resources\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Http\Resources\Show\ShowResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DownloadDetailsData */
class DownloadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $download = $this->download;
        $show = $download->show;

        return [
            'id' => $download->id,
            // Added without a name, it shows qBittorrent's, which a magnet only gets once its file list arrives
            'name' => $download->name ?? $this->torrent->name ?? $download->hash,
            'hash' => $download->hash,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'progress' => $this->torrent->progress ?? 0,
            'size_bytes' => $this->torrent->sizeBytes ?? 0,
            'downloaded_bytes' => $this->torrent->downloadedBytes ?? 0,
            'download_speed' => $this->torrent->downloadSpeed ?? 0,
            'eta_seconds' => $this->torrent?->etaSeconds,
            // Loaded with its titles only, which is all a download needs to name it
            'show' => $show === null ? null : ShowResource::make($show),
            'files' => DownloadFileResource::collection($this->files),
            'created_at' => $download->created_at,
        ];
    }
}
