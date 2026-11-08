<?php

namespace App\Http\Resources\ShowEntryWatchStatus;

use App\Dtos\ShowEntryWatchStatus\WatchStatusChangeData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WatchStatusChangeData */
class WatchStatusChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'show_id' => $this->showId,
            'show_entry_id' => $this->showEntryId,
            'status' => $this->status,
        ];
    }
}
