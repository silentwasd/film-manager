<?php

namespace App\Policies;

use App\Enums\CollectionVisibility;
use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    /**
     * Просмотр в разделе «Коллекции»: своя либо добавленная чужая, пока автор
     * её не скрыл. Посторонняя видимая коллекция открывается по своей
     * публичной ссылке, а не здесь.
     */
    public function view(User $user, Collection $collection): bool
    {
        if ($collection->user_id == $user->id) {
            return true;
        }

        return in_array($collection->visibility, CollectionVisibility::visible(), true)
            && $collection->isSavedBy($user);
    }

    /** Добавить к себе можно только чужую коллекцию, открытую не только автору. */
    public function save(User $user, Collection $collection): bool
    {
        return $collection->user_id != $user->id
            && in_array($collection->visibility, CollectionVisibility::visible(), true);
    }

    public function update(User $user, Collection $collection): bool
    {
        return $collection->user_id == $user->id;
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $collection->user_id == $user->id;
    }

    public function manageFilms(User $user, Collection $collection): bool
    {
        return $collection->user_id == $user->id;
    }
}
