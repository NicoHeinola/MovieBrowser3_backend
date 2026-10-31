<?php

namespace App\Http\Resources\ShowWatchProgress;

use App\Models\ShowWatchProgress\ShowWatchProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShowWatchProgress */
class ShowWatchProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'show_id' => $this->show_id,
            'show_entry_id' => $this->show_entry_id,
            'episode_id' => $this->episode_id,
            'updated_at' => $this->updated_at,
        ];
    }
}
