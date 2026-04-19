<?php

namespace App\Actions\Auth;

use App\Dtos\Auth\AuthTokenData;
use App\Dtos\Auth\RegisterUserData;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterUserAction
{
    use AsAction;

    public function handle(RegisterUserData $data): AuthTokenData
    {
        $user = User::create([
            'username' => $data->username,
            'password' => $data->password,
        ]);
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration === null ? null : now()->addMinutes((int) $expiration);
        $token = $user->createToken($data->tokenName, ['*'], $expiresAt)->plainTextToken;

        return AuthTokenData::from([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->fresh(),
        ]);
    }
}