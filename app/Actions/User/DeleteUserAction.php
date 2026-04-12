<?php

namespace App\Actions\User;

use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteUserAction
{
    use AsAction;

    public function handle(User $user): void
    {
        $user->delete();
    }
}
