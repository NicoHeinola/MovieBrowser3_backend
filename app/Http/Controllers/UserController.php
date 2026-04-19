<?php

namespace App\Http\Controllers;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\UpdateUserAction;
use App\Dtos\User\CreateUserData;
use App\Dtos\User\UpdateUserData;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function store(StoreUserRequest $request, CreateUserAction $createUserAction): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $createUserAction->handle(CreateUserData::from($request->validated()));

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function update(User $user, UpdateUserRequest $request, UpdateUserAction $updateUserAction): JsonResponse
    {
        $this->authorize('update', $user);

        $updatedUser = $updateUserAction->handle($user, UpdateUserData::from($request->validated()));

        return UserResource::make($updatedUser)->response();
    }

    public function destroy(User $user, DeleteUserAction $deleteUserAction): Response
    {
        $this->authorize('delete', $user);

        $deleteUserAction->handle($user);

        return response()->noContent();
    }
}
