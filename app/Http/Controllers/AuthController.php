<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutCurrentTokenAction;
use App\Actions\Auth\RegisterUserAction;
use App\Dtos\Auth\LoginUserData;
use App\Dtos\Auth\RegisterUserData;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\AuthenticatedUserResource;
use App\Http\Resources\Auth\AuthTokenResource;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUserAction): JsonResponse
    {
        $validated = $request->validated();

        $payload = $registerUserAction->handle(RegisterUserData::from([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'token_name' => $request->userAgent() ?? 'api-token',
        ]));

        return AuthTokenResource::make($payload)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, LoginUserAction $loginUserAction): JsonResponse
    {
        $validated = $request->validated();

        $payload = $loginUserAction->handle(LoginUserData::from([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'token_name' => $request->userAgent() ?? 'api-token',
        ]));

        return AuthTokenResource::make($payload)->response();
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return AuthenticatedUserResource::make($user)->response();
    }

    public function logout(Request $request, LogoutCurrentTokenAction $logoutCurrentTokenAction): Response
    {
        /** @var User $user */
        $user = $request->user();

        $logoutCurrentTokenAction->handle($user, $user->currentAccessToken());

        return response()->noContent();
    }
}
