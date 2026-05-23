<?php

namespace App\Actions\User;

use App\Dtos\User\UpdateUserData;
use App\Models\User\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUserAction
{
    use AsAction;

    public function handle(User $user, UpdateUserData $data): User
    {
        $user->fill(Arr::only($data->all(), $user->getFillable()))->save();

        return $user->fresh();
    }
}
