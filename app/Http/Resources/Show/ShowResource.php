<?php

namespace App\Http\Resources\Show;

use App\Http\Resources\ShowEntry\ShowEntryResource;
use App\Http\Resources\ShowLink\ShowLinkResource;
use App\Http\Resources\ShowTitle\ShowTitleResource;
use App\Models\Show\Show;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Show */
class ShowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'banner_url' => $this->banner_url,
            'card_image_url' => $this->card_image_url,
            'preview_url' => $this->preview_url,
            'description' => $this->description,
            'titles' => ShowTitleResource::collection($this->whenLoaded('titles')),
            'entries' => ShowEntryResource::collection($this->whenLoaded('entries')),
            'incoming_links' => ShowLinkResource::collection($this->whenLoaded('incomingLinks')),
            'outgoing_links' => ShowLinkResource::collection($this->whenLoaded('outgoingLinks')),
        ];
    }
}
