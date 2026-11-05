<?php

namespace App\Http\Resources\VideoFile;

use App\Dtos\VideoFile\VideoFileCheckData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoFileCheckData */
class VideoFileCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'running' => $this->running,
            'checked' => $this->checked,
            'total' => $this->total,
            'finished_at' => $this->finishedAt?->toJSON(),
            'error' => $this->errorCode !== null
                ? ['code' => $this->errorCode->value, 'params' => (object) []]
                : null,
        ];
    }
}
