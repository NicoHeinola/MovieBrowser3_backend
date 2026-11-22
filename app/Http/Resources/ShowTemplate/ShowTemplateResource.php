<?php

namespace App\Http\Resources\ShowTemplate;

use App\Dtos\ShowTemplate\MatchedShowTemplateData;
use App\Http\Resources\Category\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchedShowTemplateData */
class ShowTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'source_url' => $this->template->sourceUrl,
            'titles' => $this->template->titles,
            'description' => $this->template->description,
            'banner_url' => $this->template->bannerUrl,
            'card_image_url' => $this->template->cardImageUrl,
            'preview_url' => $this->template->previewUrl,
            'categories' => CategoryResource::collection($this->categories),
            'unmatched_genres' => $this->unmatchedGenres,
        ];
    }
}
