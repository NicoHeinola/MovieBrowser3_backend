<?php

namespace App\Actions\Auth;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\Concerns\AsAction;

class LogoutCurrentTokenAction
{
    use AsAction;

    public function handle(User $user, ?PersonalAccessToken $token): void
    {
        DB::transaction(function () use ($user, $token): void {
            if ($token) {
                $token->delete();
            } else {
                $user->tokens()->delete();
            }
        });
    }
}
