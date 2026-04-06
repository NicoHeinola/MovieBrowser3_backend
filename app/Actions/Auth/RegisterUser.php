<?php

namespace App\Actions\Auth;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterUser
{
    use AsAction;

    public function handle(array $attributes, string $tokenName): array
    {
        $user = User::create($attributes);
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'message' => 'Registered successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->fresh(),
        ];
    }
}
