<?php

namespace App\Actions\Auth;

use App\Dtos\Auth\AuthTokenData;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class IssueAuthTokenAction
{
    use AsAction;

    public function handle(User $user, string $tokenName): AuthTokenData
    {
        // A zero or empty expiration means tokens never expire, matching Sanctum's guard.
        $expirationMinutes = (int) config('sanctum.expiration');
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        return AuthTokenData::from([
            'token' => $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
}
