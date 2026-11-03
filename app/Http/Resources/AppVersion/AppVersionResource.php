<?php

namespace App\Http\Resources\AppVersion;

use App\Dtos\AppVersion\AppVersionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AppVersionData */
class AppVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'backend' => [
                'current' => $this->backendVersion,
                'latest' => $this->latestBackendRelease?->version,
                'release_url' => $this->latestBackendRelease?->url,
            ],
            'frontend' => [
                'latest' => $this->latestFrontendRelease?->version,
                'release_url' => $this->latestFrontendRelease?->url,
            ],
            'checked_at' => $this->checkedAt->toJSON(),
        ];
    }
}
