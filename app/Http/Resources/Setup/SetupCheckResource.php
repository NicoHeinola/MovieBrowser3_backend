<?php

namespace App\Http\Resources\Setup;

use App\Dtos\Setup\SetupCheckData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SetupCheckData */
class SetupCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ok' => $this->ok,
            'code' => $this->code,
            // An empty PHP array would encode as [], and the contract is always an object
            'params' => (object) $this->params,
        ];
    }
}
