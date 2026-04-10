<?php

namespace App\Dtos\Auth;

use App\Models\User\User;
use Cerbero\LaravelDto\Dto;

/**
 * @property string $token
 * @property string $tokenType
 * @property User $user
 */
class AuthTokenData extends Dto {}
