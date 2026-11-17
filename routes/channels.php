<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Личный канал пользователя. Сюда прилетают «что-то поменялось» —
 * в планах или в друзьях; данные клиент перечитывает сам через API,
 * так что в событии нет ничего, кроме повода.
 */
Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
