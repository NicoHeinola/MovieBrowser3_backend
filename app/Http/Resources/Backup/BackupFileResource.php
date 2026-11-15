<?php

namespace App\Http\Resources\Backup;

use App\Dtos\Backup\BackupFileData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BackupFileData */
class BackupFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'size' => $this->size,
            'created_at' => $this->createdAt->toJSON(),
        ];
    }
}
