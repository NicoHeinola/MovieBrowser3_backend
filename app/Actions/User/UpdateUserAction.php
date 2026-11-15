<?php

namespace App\Actions\User;

use App\Dtos\User\UpdateUserData;
use App\Enums\ValidationErrorCode;
use App\Models\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUserAction
{
    use AsAction;

    public function handle(User $user, UpdateUserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill(Arr::only($data->all(), $user->getFillable()));

            // Without an admin nobody can reach the admin pages again. The admin rows are locked so two demotions can't
            // both pass, and counted in PHP because Postgres rejects FOR UPDATE on count(*)
            if ($user->isDirty('is_admin') && !$user->is_admin && User::query()->where('is_admin', true)->lockForUpdate()->pluck('id')->count() <= 1) {
                throw ValidationException::withMessages(['is_admin' => [ValidationErrorCode::LastAdmin->value]]);
            }

            $user->save();

            return $user->fresh();
        });
    }
}
