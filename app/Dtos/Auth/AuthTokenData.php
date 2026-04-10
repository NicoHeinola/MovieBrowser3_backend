<?php

namespace App\Dtos\Auth;

use App\Models\User\User;
use Spatie\LaravelData\Data;

class AuthTokenData extends Data
{
    public function __construct(
        public string $token,
        public string $tokenType,
        public User $user,
    ) {}
}
