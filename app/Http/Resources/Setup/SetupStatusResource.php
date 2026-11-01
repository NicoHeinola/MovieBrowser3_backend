<?php

namespace App\Http\Resources\Setup;

use App\Dtos\Setup\SetupStatusData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SetupStatusData */
class SetupStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'completed_at' => $this->completedAt?->toJSON(),
            'media_folders' => $this->mediaFolders,
            'detected_vlc_media_player_path' => $this->detectedVlcMediaPlayerPath,
        ];
    }
}
