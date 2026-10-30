<?php

namespace App\Http\Resources\ShowSection;

use App\Http\Resources\Category\CategoryResource;
use App\Models\ShowSection\ShowSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowSection */
class ShowSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'category' => CategoryResource::make($this->whenLoaded('category')),
        ];
    }
}
