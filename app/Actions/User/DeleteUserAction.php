<?php

namespace App\Actions\User;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteUserAction
{
    use AsAction;

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->delete();
        });
    }
}
