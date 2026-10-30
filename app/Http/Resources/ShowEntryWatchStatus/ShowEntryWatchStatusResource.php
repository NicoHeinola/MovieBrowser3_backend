<?php

namespace App\Http\Resources\ShowEntryWatchStatus;

use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowEntryWatchStatus */
class ShowEntryWatchStatusResource extends JsonResource
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
