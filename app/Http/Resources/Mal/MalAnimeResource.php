<?php

namespace App\Http\Resources\Mal;

use App\Dtos\Mal\MalAnimeData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MalAnimeData */
class MalAnimeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'image_url' => $this->imageUrl,
            'media_type' => $this->mediaType,
            'start_year' => $this->startYear,
            'status' => $this->status,
            'score' => $this->score,
        ];
    }
}
