<?php

namespace Tests\Feature\Management;

use App\Events\UserDataChanged;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\Friendship;
use App\Models\User;
use App\Models\WatchPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WatchPlanTest extends TestCase
{
    use RefreshDatabase;

    private function befriend(User $a, User $b): void
    {
        Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted_at' => now()]);
    }

    private function plan(User $by, array $others = [], ?Film $film = null): int
    {
        return $this->actingAs($by)->postJson('/api/management/watch-plans', [
            'film_id' => ($film ?? Film::factory()->create())->id,
            'participants' => array_map(fn (User $u) => $u->id, $others),
        ])->assertCreated()->json('data.id');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/management/watch-plans')->assertUnauthorized();
    }

    public function test_store_with_friends_and_everyone_sees_it(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $this->befriend($me, $friend);

        $id = $this->plan($me, [$friend]);

        $this->actingAs($friend)->getJson('/api/management/watch-plans')
            ->assertJsonPath('planned.0.id', $id)
            ->assertJsonCount(2, 'planned.0.participants');

        $this->actingAs($stranger)->getJson('/api/management/watch-plans')
            ->assertJsonCount(0, 'planned');
    }

    public function test_solo_plan_is_allowed(): void
    {
        $me = User::factory()->create();
        $id = $this->plan($me);

        $this->actingAs($me)->getJson('/api/management/watch-plans')->assertJsonPath('planned.0.id', $id);
    }

    public function test_only_friends_can_be_invited(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();
        $pending = User::factory()->create();
        Friendship::create(['user_id' => $me->id, 'friend_id' => $pending->id]);

        foreach ([$stranger, $pending] as $user) {
            $this->actingAs($me)->postJson('/api/management/watch-plans', [
                'film_id' => Film::factory()->create()->id,
                'participants' => [$user->id],
            ])->assertStatus(422)->assertJsonValidationErrors('participants');
        }

        $this->assertDatabaseCount('watch_plans', 0);
    }

    public function test_status_flow_sets_dates_and_syncs_watchlist(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $film = Film::factory()->create();

        // У друга фильм уже в «Буду смотреть».
        FilmWatcher::create(['film_id' => $film->id, 'watcher_id' => $friend->id, 'status' => 'to-watch']);

        $id = $this->plan($me, [$friend], $film);

        Carbon::setTestNow('2026-11-15 20:00');
        $this->actingAs($friend)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watching'])
            ->assertOk()
            ->assertJsonPath('data.started_at', '2026-11-15');

        $this->assertDatabaseHas('film_watchers', ['film_id' => $film->id, 'watcher_id' => $me->id, 'status' => 'must-finish']);
        $this->assertDatabaseHas('film_watchers', ['film_id' => $film->id, 'watcher_id' => $friend->id, 'status' => 'must-finish']);

        Carbon::setTestNow('2026-11-17 23:00');
        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watched'])
            ->assertOk()
            ->assertJsonPath('data.started_at', '2026-11-15')
            ->assertJsonPath('data.finished_at', '2026-11-17');

        $this->assertDatabaseHas('film_watchers', ['film_id' => $film->id, 'watcher_id' => $me->id, 'status' => 'watched']);
        $this->assertDatabaseHas('film_watchers', ['film_id' => $film->id, 'watcher_id' => $friend->id, 'status' => 'watched']);
        $this->assertDatabaseCount('film_watchers', 2);

        $this->actingAs($me)->getJson('/api/management/watch-plans')->assertJsonPath('watched.0.id', $id);

        Carbon::setTestNow();
    }

    public function test_status_with_stale_from_is_rejected(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $id = $this->plan($me, [$friend]);

        $this->actingAs($friend)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watched', 'from' => 'planned'])
            ->assertOk();

        // У меня на экране запись ещё «в планах» — «Начинаем» не должен откатить друга.
        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watching', 'from' => 'planned'])
            ->assertStatus(409);

        $this->assertSame('watched', WatchPlan::find($id)->status->value);
    }

    public function test_changes_are_broadcast_to_every_participant(): void
    {
        Event::fake([UserDataChanged::class]);

        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $id = $this->plan($me, [$friend]);

        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watching'])->assertOk();

        Event::assertDispatched(UserDataChanged::class, function (UserDataChanged $e) use ($me, $friend) {
            $channels = collect($e->broadcastOn())->map(fn ($c) => $c->name)->sort()->values()->all();
            $expected = collect(["private-users.{$me->id}", "private-users.{$friend->id}"])->sort()->values()->all();

            return $e->topic === 'plans' && $channels === $expected;
        });
    }

    public function test_removed_participant_is_notified(): void
    {
        Event::fake([UserDataChanged::class]);

        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $id = $this->plan($me, [$friend]);

        $this->actingAs($me)->patchJson("/api/management/watch-plans/{$id}", ['participants' => []])->assertOk();

        Event::assertDispatched(UserDataChanged::class, fn (UserDataChanged $e) => in_array($friend->id, $e->userIds));
    }

    public function test_broadcast_failure_does_not_break_the_action(): void
    {
        // Reverb недоступен: порт, где никто не слушает. Лог в тестах не пишем.
        config([
            'logging.default' => 'null',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k',
            'broadcasting.connections.reverb.secret' => 's',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 9,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
            'broadcasting.connections.reverb.client_options' => ['timeout' => 2, 'connect_timeout' => 2],
        ]);

        $me = User::factory()->create();
        $id = $this->plan($me);

        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watched'])->assertOk();
        $this->assertSame('watched', WatchPlan::find($id)->status->value);
    }

    public function test_rewatch_does_not_downgrade_watched(): void
    {
        $me = User::factory()->create();
        $film = Film::factory()->create();
        FilmWatcher::create(['film_id' => $film->id, 'watcher_id' => $me->id, 'status' => 'watched']);

        $id = $this->plan($me, [], $film);

        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watching'])->assertOk();

        $this->assertDatabaseHas('film_watchers', ['film_id' => $film->id, 'watcher_id' => $me->id, 'status' => 'watched']);
    }

    public function test_back_to_planned_clears_dates(): void
    {
        $me = User::factory()->create();
        $id = $this->plan($me);

        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watching']);
        $this->actingAs($me)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'planned'])
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.finished_at', null);
    }

    public function test_reorder_permutes_only_visible_plans(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $other = User::factory()->create();
        $this->befriend($me, $friend);
        $this->befriend($friend, $other);

        $a = $this->plan($me, [$friend]);
        $hidden = $this->plan($friend, [$other]); // мне не видна
        $b = $this->plan($me, [$friend]);
        $c = $this->plan($me);

        $this->actingAs($me)->putJson('/api/management/watch-plans/reorder', ['plans' => [$c, $a, $b]])
            ->assertNoContent();

        $this->actingAs($me)->getJson('/api/management/watch-plans')
            ->assertJsonPath('planned.0.id', $c)
            ->assertJsonPath('planned.1.id', $a)
            ->assertJsonPath('planned.2.id', $b);

        // Переставились только позиции моих записей (1, 3, 4 → c, a, b);
        // запись, которую я не вижу, осталась на своей позиции 2.
        $this->assertSame(2, WatchPlan::find($hidden)->position);

        $ids = collect($this->actingAs($friend)->getJson('/api/management/watch-plans')->json('planned'))->pluck('id')->all();
        $this->assertSame([$hidden, $a, $b], $ids);
    }

    public function test_reorder_rejects_foreign_plans(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();
        $mine = $this->plan($me);
        $theirs = $this->plan($stranger);

        $this->actingAs($me)->putJson('/api/management/watch-plans/reorder', ['plans' => [$theirs, $mine]])
            ->assertStatus(422);
    }

    public function test_friend_filter(): void
    {
        $me = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->befriend($me, $a);
        $this->befriend($me, $b);

        $withA = $this->plan($me, [$a]);
        $this->plan($me, [$b]);
        $this->plan($me);

        $this->actingAs($me)->getJson("/api/management/watch-plans?friend_id={$a->id}")
            ->assertJsonCount(1, 'planned')
            ->assertJsonPath('planned.0.id', $withA);
    }

    public function test_non_participant_gets_404(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();
        $id = $this->plan($me);

        $this->actingAs($stranger)->putJson("/api/management/watch-plans/{$id}/status", ['status' => 'watched'])->assertNotFound();
        $this->actingAs($stranger)->patchJson("/api/management/watch-plans/{$id}", ['note' => 'x'])->assertNotFound();
        $this->actingAs($stranger)->deleteJson("/api/management/watch-plans/{$id}")->assertNotFound();
        $this->assertDatabaseCount('film_watchers', 0);
    }

    public function test_update_keeps_participants_invited_by_others(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $friendsFriend = User::factory()->create();
        $this->befriend($me, $friend);
        $this->befriend($friend, $friendsFriend);

        $id = $this->plan($friend, [$me, $friendsFriend]);

        // Я не дружу с третьим, но он уже в плане — оставить его можно.
        $this->actingAs($me)->patchJson("/api/management/watch-plans/{$id}", [
            'note' => 'В пятницу',
            'participants' => [$friend->id, $friendsFriend->id],
        ])->assertOk()->assertJsonPath('data.note', 'В пятницу')->assertJsonCount(3, 'data.participants');

        // А убрать — тоже можно.
        $this->actingAs($me)->patchJson("/api/management/watch-plans/{$id}", ['participants' => [$friend->id]])
            ->assertJsonCount(2, 'data.participants');

        // Вернуть уже нельзя — он мне не друг.
        $this->actingAs($me)->patchJson("/api/management/watch-plans/{$id}", ['participants' => [$friend->id, $friendsFriend->id]])
            ->assertStatus(422);
    }

    public function test_leave_and_last_leave_deletes(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $id = $this->plan($me, [$friend]);

        $this->actingAs($me)->deleteJson("/api/management/watch-plans/{$id}/leave")->assertNoContent();
        $this->assertDatabaseHas('watch_plans', ['id' => $id]);
        $this->actingAs($me)->getJson('/api/management/watch-plans')->assertJsonCount(0, 'planned');

        $this->actingAs($friend)->deleteJson("/api/management/watch-plans/{$id}/leave")->assertNoContent();
        $this->assertDatabaseMissing('watch_plans', ['id' => $id]);
    }

    public function test_destroy_removes_for_everyone(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $id = $this->plan($me, [$friend]);

        $this->actingAs($friend)->deleteJson("/api/management/watch-plans/{$id}")->assertNoContent();

        $this->assertDatabaseCount('watch_plans', 0);
        $this->assertDatabaseCount('watch_plan_user', 0);
    }

    public function test_unfriending_keeps_existing_plans(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($me, $friend);
        $id = $this->plan($me, [$friend]);

        $this->actingAs($me)->deleteJson("/api/management/friends/{$friend->id}");

        $this->actingAs($friend)->getJson('/api/management/watch-plans')->assertJsonPath('planned.0.id', $id);
        $this->assertSame(1, WatchPlan::count());
    }
}
