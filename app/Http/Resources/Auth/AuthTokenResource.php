<?php

namespace App\Http\Resources\Auth;

use App\Dtos\Auth\AuthTokenData;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuthTokenData */
class AuthTokenResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'token_type' => $this->tokenType,
            'user' => UserResource::make($this->user)->resolve($request),
        ];
    }
}
