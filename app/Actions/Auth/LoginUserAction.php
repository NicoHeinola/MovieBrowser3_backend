<?php

namespace App\Actions\Auth;

use App\Dtos\Auth\AuthTokenData;
use App\Dtos\Auth\LoginUserData;
use App\Enums\ValidationErrorCode;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class LoginUserAction
{
    use AsAction;

    public function __construct(private readonly IssueAuthTokenAction $issueAuthTokenAction) {}

    public function handle(LoginUserData $data): AuthTokenData
    {
        $user = User::where('username', $data->username)->first();

        $startedAt = microtime(true);

        $hasLoginSucceeded = $user && Hash::check($data->password, $user->password);

        $loginWaitDuration = max(0, (int) config('auth.login_wait_duration_ms', 1000));

        // To prevent brute-force attacks, we want to make sure that each login attempt takes at least a certain amount of time.
        $remainingDelay = $loginWaitDuration - (int) round((microtime(true) - $startedAt) * 1000);

        if ($remainingDelay > 0) {
            usleep($remainingDelay * 1000);
        }

        if (!$hasLoginSucceeded) {
            throw ValidationException::withMessages([
                'username' => [ValidationErrorCode::InvalidCredentials->value],
            ]);
        }

        return $this->issueAuthTokenAction->handle($user, $data->tokenName);
    }
}
