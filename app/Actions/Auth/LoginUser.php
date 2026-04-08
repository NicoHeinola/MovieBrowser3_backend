<?php

namespace App\Actions\Auth;

use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class LoginUser
{
    use AsAction;

    public function handle(string $username, string $password, string $tokenName): array
    {
        $user = User::where('username', $username)->first();

        $startedAt = microtime(true);

        $hasLoginSucceeded = $user && Hash::check($password, $user->password);

        $loginWaitDuration = max(0, (int) config('auth.login_wait_duration_ms', 1000));

        // To prevent brute-force attacks, we want to make sure that each login attempt takes at least a certain amount of time.
        $remainingDelay = $loginWaitDuration - (int) round((microtime(true) - $startedAt) * 1000);

        if ($remainingDelay > 0) {
            usleep($remainingDelay * 1000);
        }

        if (!$hasLoginSucceeded) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration === null ? null : now()->addMinutes((int) $expiration);

        return [
            'message' => 'Authenticated successfully.',
            'token' => $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}
