<?php

namespace App\Policies;

use App\Models\User\User;

class UserPolicy
{
    public function create(User $actor): bool
    {
        return $actor->is_admin;
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->is_admin || $actor->is($subject);
    }

    public function delete(User $actor, User $subject): bool
    {
        return $actor->is_admin && !$actor->is($subject);
    }
}
