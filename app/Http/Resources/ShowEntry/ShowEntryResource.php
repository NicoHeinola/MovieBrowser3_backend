<?php

namespace App\Http\Resources\ShowEntry;

use App\Http\Resources\Episode\EpisodeResource;
use App\Http\Resources\ShowEntryWatchStatus\ShowEntryWatchStatusResource;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowEntry */
class ShowEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'show_id' => $this->show_id,
            'type' => $this->type,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'watch_status' => ShowEntryWatchStatusResource::make($this->whenLoaded('watchStatuses', fn () => $this->watchStatuses->first())),
            'episodes' => EpisodeResource::collection($this->whenLoaded('episodes')),
        ];
    }
}
