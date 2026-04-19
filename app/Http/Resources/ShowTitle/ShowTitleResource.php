<?php

namespace App\Http\Resources\ShowTitle;

use App\Models\ShowTitle\ShowTitle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowTitle */
class ShowTitleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'show_id' => $this->show_id,
            'title' => $this->title,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
