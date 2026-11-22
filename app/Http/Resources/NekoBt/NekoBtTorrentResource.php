<?php

namespace App\Http\Resources\NekoBt;

use App\Dtos\NekoBt\NekoBtTorrentData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NekoBtTorrentData */
class NekoBtTorrentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'size_bytes' => $this->sizeBytes,
            'uploaded_at' => $this->uploadedAt->toJSON(),
            'seeders' => $this->seeders,
            'leechers' => $this->leechers,
            'downloads' => $this->downloads,
            'comments' => $this->comments,
            'is_batch' => $this->isBatch,
            'uploader' => $this->uploader,
            'groups' => $this->groups,
            'audio_languages' => $this->audioLanguages,
            'subtitle_languages' => $this->subtitleLanguages,
            'level' => $this->level?->value,
            'video_source' => $this->videoSource?->value,
            'video_codec' => $this->videoCodec?->value,
            'is_original_translation' => $this->isOriginalTranslation,
            'is_machine_translation' => $this->isMachineTranslation,
            'is_hardsub' => $this->isHardsub,
            'magnet_url' => $this->magnetUrl,
            'torrent_url' => $this->torrentUrl,
            'view_url' => $this->viewUrl,
            'media_url' => $this->mediaUrl,
        ];
    }
}
