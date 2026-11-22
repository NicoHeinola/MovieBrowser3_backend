<?php

namespace App\Http\Resources\Setting;

use App\Models\Setting\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Setting */
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSecret = $this->type === 'secret';

        // A secret never leaves the backend: every signed-in user can list settings
        return [
            'key' => $this->key,
            'value' => $isSecret ? null : $this->value,
            'type' => $this->type,
            'is_set' => $this->when($isSecret, fn (): bool => filled($this->value)),
            'updated_at' => $this->updated_at,
        ];
    }
}
