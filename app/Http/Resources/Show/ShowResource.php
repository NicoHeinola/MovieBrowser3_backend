<?php

namespace App\Http\Resources\Show;

use App\Models\Show\Show;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Show */
class ShowResource extends JsonResource
{
    public static $wrap = null;

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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'titles' => ShowTitleResource::collection($this->whenLoaded('titles')),
        ];
    }
}
