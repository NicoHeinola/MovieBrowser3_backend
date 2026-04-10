<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutCurrentToken;
use App\Actions\Auth\RegisterUser;
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
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $validated = $request->validated();

        $payload = $registerUser->handle(RegisterUserData::from([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'token_name' => $request->userAgent() ?? 'api-token',
        ]));

        return AuthTokenResource::make($payload)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, LoginUser $loginUser): JsonResponse
    {
        $validated = $request->validated();

        $payload = $loginUser->handle(LoginUserData::from([
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

    public function logout(Request $request, LogoutCurrentToken $logoutCurrentToken): Response
    {
        /** @var User $user */
        $user = $request->user();

        $logoutCurrentToken->handle($user, $user->currentAccessToken());

        return response()->noContent();
    }
}
