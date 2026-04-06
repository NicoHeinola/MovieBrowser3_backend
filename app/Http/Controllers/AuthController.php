<?php

namespace App\Http\Controllers;

use App\Actions\Auth\GetAuthenticatedUser;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutCurrentToken;
use App\Actions\Auth\RegisterUser;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $validated = $request->validated();

        $payload = $registerUser->handle(
            Arr::only($validated, ['name', 'email', 'password']),
            $validated['device_name'] ?? $request->userAgent() ?? 'api-token',
        );

        return response()->json($payload, 201);
    }

    public function login(LoginRequest $request, LoginUser $loginUser): JsonResponse
    {
        $validated = $request->validated();

        $payload = $loginUser->handle(
            $validated['email'],
            $validated['password'],
            $validated['device_name'] ?? $request->userAgent() ?? 'api-token',
        );

        return response()->json($payload);
    }

    public function me(Request $request, GetAuthenticatedUser $getAuthenticatedUser): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($getAuthenticatedUser->handle($user));
    }

    public function logout(Request $request, LogoutCurrentToken $logoutCurrentToken): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $logoutCurrentToken->handle($user, $user->currentAccessToken()),
        );
    }
}
