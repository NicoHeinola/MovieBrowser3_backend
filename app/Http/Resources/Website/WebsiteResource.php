<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\WebsiteTag\WebsiteTagResource;
use App\Models\Website\Website;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Website */
class WebsiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'icon' => $this->icon,
            'description' => $this->description,
            'tags' => WebsiteTagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
