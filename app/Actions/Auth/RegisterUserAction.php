<?php

namespace App\Actions\Auth;

use App\Dtos\Auth\AuthTokenData;
use App\Dtos\Auth\RegisterUserData;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterUserAction
{
    use AsAction;

    public function __construct(private readonly IssueAuthTokenAction $issueAuthTokenAction) {}

    public function handle(RegisterUserData $data): AuthTokenData
    {
        $user = User::create([
            'username' => $data->username,
            'password' => $data->password,
        ]);

        return $this->issueAuthTokenAction->handle($user->fresh(), $data->tokenName);
    }
}
