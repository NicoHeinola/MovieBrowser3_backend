<?php

namespace App\Http\Resources\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeleteShowsResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'deleted_count' => $this->resource['deleted_count'],
        ];
    }
}
