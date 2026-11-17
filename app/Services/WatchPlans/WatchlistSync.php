<?php

namespace App\Services\WatchPlans;

use App\Enums\FilmWatchStatus;
use App\Enums\WatchPlanStatus;
use App\Models\FilmWatcher;
use App\Models\WatchPlan;

/**
 * Перенос статуса плана в «Мои фильмы» каждого участника — как будто каждый
 * отметил сам. Назад ничего не откатывается: вернули запись из «Посмотрели»
 * в «Смотрим» — «Просмотрено» у людей остаётся.
 */
class WatchlistSync
{
    public function apply(WatchPlan $plan): void
    {
        $target = match ($plan->status) {
            WatchPlanStatus::Watching => FilmWatchStatus::MustFinish,
            WatchPlanStatus::Watched => FilmWatchStatus::Watched,
            WatchPlanStatus::Planned => null,
        };

        if ($target === null) {
            return;
        }

        foreach ($plan->participants()->pluck('users.id') as $userId) {
            $records = FilmWatcher::query()
                ->where('film_id', $plan->film_id)
                ->where('watcher_id', $userId)
                ->get();

            if ($records->isEmpty()) {
                FilmWatcher::create([
                    'film_id' => $plan->film_id,
                    'watcher_id' => $userId,
                    'status' => $target->value,
                ]);

                continue;
            }

            foreach ($records as $record) {
                // Пересмотр уже просмотренного: «Досмотреть» поверх «Просмотрено»
                // было бы шагом назад.
                if ($target === FilmWatchStatus::MustFinish && $record->status === FilmWatchStatus::Watched->value) {
                    continue;
                }

                $record->update(['status' => $target->value]);
            }
        }
    }
}
