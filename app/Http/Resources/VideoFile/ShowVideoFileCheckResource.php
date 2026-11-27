<?php

namespace App\Http\Resources\VideoFile;

use App\Dtos\VideoFile\ShowVideoFileCheckData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowVideoFileCheckData */
class ShowVideoFileCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'missing_video_files_count' => $this->missingVideoFilesCount,
            'unknown_video_durations_count' => $this->unknownVideoDurationsCount,
        ];
    }
}
