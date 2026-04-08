<?php

namespace App\Actions\Auth;

use App\Models\User\User;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\Concerns\AsAction;

class LogoutCurrentToken
{
    use AsAction;

    public function handle(User $user, ?PersonalAccessToken $token): array
    {
        if ($token) {
            $token->delete();
        } else {
            $user->tokens()->delete();
        }

        return [
            'message' => 'Logged out successfully.',
        ];
    }
}
