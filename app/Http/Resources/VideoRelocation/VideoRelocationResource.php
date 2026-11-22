<?php

namespace App\Http\Resources\VideoRelocation;

use App\Dtos\VideoRelocation\VideoRelocationData;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoRelocationData */
class VideoRelocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'running' => $this->running,
            'reverting' => $this->reverting,
            'moved' => $this->moved,
            'total' => $this->total,
            'from_path' => $this->fromPath,
            'to_path' => $this->toPath,
            'finished_at' => $this->finishedAt?->toJSON(),
            'error' => $this->errorCode !== null ? ApiErrorDetail::make($this->errorCode, array_filter(['path' => $this->errorPath])) : null,
        ];
    }
}
