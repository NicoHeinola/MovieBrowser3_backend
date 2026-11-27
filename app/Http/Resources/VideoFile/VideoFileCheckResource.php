<?php

namespace App\Http\Resources\VideoFile;

use App\Dtos\VideoFile\VideoFileCheckData;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoFileCheckData */
class VideoFileCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $error = null;

        if ($this->errorCode !== null) {
            $error = ApiErrorDetail::make($this->errorCode);
        }

        return [
            'running' => $this->running,
            'checked' => $this->checked,
            'total' => $this->total,
            'finished_at' => $this->finishedAt?->toJSON(),
            'error' => $error,
            'missing_video_files_count' => $this->missingVideoFilesCount,
            'unknown_video_durations_count' => $this->unknownVideoDurationsCount,
        ];
    }
}
