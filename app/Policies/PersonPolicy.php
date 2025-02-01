<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function update(User $user, Person $person): bool
    {
        return $person->author_id == $user->id || $user->role == UserRole::Admin;
    }

    public function delete(User $user, Person $person): bool
    {
        return ($person->author_id == $user->id && $person->films()->count() == 0) ||
               $user->role == UserRole::Admin;
    }
}
