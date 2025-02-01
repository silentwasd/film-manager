<?php

namespace App\Policies;

use App\Models\FilmWatcher;
use App\Models\User;

class FilmWatcherPolicy
{
    public function update(User $user, FilmWatcher $watcher): bool
    {
        return $watcher->watcher_id == $user->id;
    }

    public function delete(User $user, FilmWatcher $watcher): bool
    {
        return $watcher->watcher_id == $user->id;
    }
}
