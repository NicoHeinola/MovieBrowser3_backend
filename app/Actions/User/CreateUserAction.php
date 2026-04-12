<?php

namespace App\Actions\User;

use App\Dtos\User\CreateUserData;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateUserAction
{
    use AsAction;

    public function handle(CreateUserData $data): User
    {
        return User::create([
            'username' => $data->username,
            'password' => $data->password,
            'is_admin' => $data->isAdmin,
        ])->fresh();
    }
}
