<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\FilmWatcher;
use App\Models\User;

class FilmPersonPolicy
{
    public function create(User $user, Film $film): bool
    {
        return $film->author_id == $user->id || $user->role == UserRole::Admin;
    }

    public function update(User $user, FilmPerson $person): bool
    {
        return $person->film->author_id == $user->id || $user->role == UserRole::Admin;
    }

    public function delete(User $user, FilmPerson $person): bool
    {
        return $person->film->author_id == $user->id || $user->role == UserRole::Admin;
    }
}
