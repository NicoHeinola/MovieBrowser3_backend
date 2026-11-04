<?php

namespace App\Http\Resources\Setup;

use App\Dtos\Setup\SetupCheckData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The checked setup values, keyed by setting key: { "video_base_path": { "ok", "code", "params" }, ... }
 *
 * @property array<string, SetupCheckData> $resource
 */
class SetupChecksResource extends JsonResource
{
    /**
     * @return array<string, SetupCheckResource>
     */
    public function toArray(Request $request): array
    {
        return array_map(fn (SetupCheckData $check): SetupCheckResource => SetupCheckResource::make($check), $this->resource);
    }
}
