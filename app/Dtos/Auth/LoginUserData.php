<?php

namespace App\Dtos\Auth;

use Spatie\LaravelData\Data;

class LoginUserData extends Data
{
    public function __construct(
        public string $username,
        public string $password,
        public string $tokenName,
    ) {}
}
