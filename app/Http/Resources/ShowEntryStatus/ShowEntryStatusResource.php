<?php

namespace App\Http\Resources\ShowEntryStatus;

use App\Models\ShowEntryStatus\ShowEntryStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowEntryStatus */
class ShowEntryStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'show_entry_id' => $this->show_entry_id,
            'status' => $this->status,
        ];
    }
}
