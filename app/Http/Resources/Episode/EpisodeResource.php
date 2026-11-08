<?php

namespace App\Http\Resources\Episode;

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Episode */
class EpisodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'show_entry_id' => $this->show_entry_id,

            'name' => $this->name,
            'filename' => $this->filename,
            'has_video_file' => $this->has_video_file,
            'video_file_size' => $this->video_file_size,
            'sequence_number' => $this->sequence_number,
            'file_path' => ResolveEpisodeVideoPathAction::run($this->resource),
        ];
    }
}
