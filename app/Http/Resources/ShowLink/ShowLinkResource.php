<?php

namespace App\Http\Resources\ShowLink;

use App\Models\ShowLink\ShowLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowLink */
class ShowLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_show_id' => $this->source_show_id,
            'target_show_id' => $this->target_show_id,
            'type' => $this->type,
        ];
    }
}
