<?php

namespace App\Support;

use App\Events\UserDataChanged;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Живые обновления — приятный бонус, а не часть действия. Лёг Reverb —
 * план всё равно сохраняется, просто друг увидит его после перезагрузки.
 */
class Live
{
    /**
     * @param  array<int, int|string>  $userIds
     * @param  'plans'|'friends'  $topic
     */
    public static function notify(array $userIds, string $topic): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return;
        }

        // Только после коммита: иначе клиент перечитает данные раньше, чем они появятся.
        DB::afterCommit(function () use ($userIds, $topic) {
            try {
                broadcast(new UserDataChanged($userIds, $topic));
            } catch (Throwable $e) {
                // Даже недоступный лог не должен уронить запрос.
                try {
                    report($e);
                } catch (Throwable) {
                }
            }
        });
    }
}
