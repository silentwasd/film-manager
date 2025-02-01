<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\User;

class FilmPolicy
{
    public function update(User $user, Film $film): bool
    {
        return $film->author_id == $user->id || $user->role == UserRole::Admin;
    }

    public function delete(User $user, Film $film): bool
    {
        return ($film->author_id == $user->id && $film->watchers()->whereNot('watcher_id', $user->id)->count() == 0) ||
               $user->role == UserRole::Admin;
    }
}
