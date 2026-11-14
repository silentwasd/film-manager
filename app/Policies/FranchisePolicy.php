<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Franchise;
use App\Models\User;

class FranchisePolicy
{
    public function update(User $user, Franchise $franchise): bool
    {
        return $franchise->author_id == $user->id || $user->role == UserRole::Admin;
    }

    public function delete(User $user, Franchise $franchise): bool
    {
        return ($franchise->author_id == $user->id && $franchise->films()->count() == 0) ||
               $user->role == UserRole::Admin;
    }
}
