<?php

namespace App\Actions\Auth;

use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAuthenticatedUser
{
    use AsAction;

    public function handle(User $user): array
    {
        return [
            'user' => $user,
        ];
    }
}
