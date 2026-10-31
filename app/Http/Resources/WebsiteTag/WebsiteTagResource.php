<?php

namespace App\Http\Resources\WebsiteTag;

use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebsiteTag */
class WebsiteTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
