<?php

namespace App\Actions\Auth;

use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterUser
{
    use AsAction;

    public function handle(array $attributes, string $tokenName): array
    {
        $user = User::create($attributes);
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration === null ? null : now()->addMinutes((int) $expiration);
        $token = $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        return [
            'message' => 'Registered successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->fresh(),
        ];
    }
}
