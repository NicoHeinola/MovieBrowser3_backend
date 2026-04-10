<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\UserResource;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AuthenticatedUserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => UserResource::make($this->resource)->resolve($request),
        ];
    }
}
