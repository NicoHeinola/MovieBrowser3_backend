<?php

namespace App\Dtos\User;

use Spatie\LaravelData\Data;

class CreateUserData extends Data
{
    public function __construct(
        public string $username,
        public string $password,
        public bool $isAdmin = false,
    ) {}
}
