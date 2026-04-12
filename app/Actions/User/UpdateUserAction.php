<?php

namespace App\Actions\User;

use App\Dtos\User\UpdateUserData;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateUserAction
{
    use AsAction;

    public function handle(User $user, UpdateUserData $data): User
    {
        $attributes = [];

        if (!$data->username instanceof Optional) {
            $attributes['username'] = $data->username;
        }

        if (!$data->password instanceof Optional) {
            $attributes['password'] = $data->password;
        }

        if (!$data->isAdmin instanceof Optional) {
            $attributes['is_admin'] = $data->isAdmin;
        }

        if ($attributes !== []) {
            $user->fill($attributes);
            $user->save();
        }

        return $user->fresh();
    }
}
