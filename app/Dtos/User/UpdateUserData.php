<?php

namespace App\Dtos\User;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateUserData extends Data
{
    public function __construct(
        public string|Optional $username,
        public string|Optional $password,
        public bool|Optional $isAdmin,
    ) {}
}
