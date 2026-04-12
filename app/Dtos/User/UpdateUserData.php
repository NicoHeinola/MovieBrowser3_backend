<?php

namespace App\Dtos\User;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateUserData extends Data
{
    public function __construct(
        public string|Optional $username,
        public string|Optional $password,
        public bool|Optional $isAdmin,
    ) {}
}
