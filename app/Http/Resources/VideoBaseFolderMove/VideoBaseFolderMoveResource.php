<?php

namespace App\Http\Resources\VideoBaseFolderMove;

use App\Dtos\VideoBaseFolderMove\VideoBaseFolderMoveData;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoBaseFolderMoveData */
class VideoBaseFolderMoveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $error = null;

        if ($this->errorCode !== null) {
            // The file that could not be moved, when the move knows it
            $params = array_filter(['path' => $this->errorPath]);
            $error = ApiErrorDetail::make($this->errorCode, $params);
        }

        return [
            'running' => $this->running,
            'reverting' => $this->reverting,
            'moved' => $this->moved,
            'total' => $this->total,
            'from_path' => $this->fromPath,
            'to_path' => $this->toPath,
            'finished_at' => $this->finishedAt?->toJSON(),
            'error' => $error,
        ];
    }
}
