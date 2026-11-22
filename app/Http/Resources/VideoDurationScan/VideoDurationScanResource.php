<?php

namespace App\Http\Resources\VideoDurationScan;

use App\Dtos\VideoDurationScan\VideoDurationScanData;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoDurationScanData */
class VideoDurationScanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'running' => $this->running,
            'read' => $this->read,
            'total' => $this->total,
            'finished_at' => $this->finishedAt?->toJSON(),
            'error' => $this->errorCode !== null ? ApiErrorDetail::make($this->errorCode) : null,
            'unknown_durations_count' => $this->unknownDurationsCount,
        ];
    }
}
