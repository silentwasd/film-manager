<?php

namespace Tests\Feature\Mcp;

use App\Enums\CollectionVisibility;
use App\Mcp\Servers\FilmManagerServer;
use App\Mcp\Tools\AddFilmNote;
use App\Mcp\Tools\DeleteCollection;
use App\Mcp\Tools\DeleteFilmNote;
use App\Mcp\Tools\GetCollection;
use App\Mcp\Tools\GetFilm;
use App\Mcp\Tools\GetWatchlist;
use App\Mcp\Tools\ListCollections;
use App\Mcp\Tools\ManageCollectionFilms;
use App\Mcp\Tools\RemoveFromWatchlist;
use App\Mcp\Tools\SaveCollection;
use App\Mcp\Tools\SetFilmReaction;
use App\Mcp\Tools\SetWatchStatus;
use App\Mcp\Tools\WhoAmI;
use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\Feedback;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalToolsTest extends TestCase
{
    use RefreshDatabase;

    // --- whoami ---

    public function test_whoami_reports_connected_account(): void
    {
        $user = User::factory()->create(['name' => 'Башкирский Кот']);
        $film = Film::factory()->create();
        FilmWatcher::factory()->create([
            'watcher_id' => $user->id,
            'film_id' => $film->id,
            'status' => 'watched',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(WhoAmI::class, [])
            ->assertOk()
            ->assertSee(['Башкирский Кот', '"watched":1']);
    }

    // --- watchlist ---

    public function test_watchlist_shows_only_own_records(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Film::factory()->create(['name' => 'Мой фильм']);
        $theirs = Film::factory()->create(['name' => 'Чужой фильм']);

        FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $mine->id]);
        FilmWatcher::factory()->create(['watcher_id' => $other->id, 'film_id' => $theirs->id]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, [])
            ->assertOk()
            ->assertSee('Мой фильм')
            ->assertDontSee('Чужой фильм');
    }

    public function test_watchlist_filters_by_status(): void
    {
        $user = User::factory()->create();

        FilmWatcher::factory()->create([
            'watcher_id' => $user->id,
            'film_id' => Film::factory()->create(['name' => 'Досмотрен'])->id,
            'status' => 'watched',
        ]);

        FilmWatcher::factory()->create([
            'watcher_id' => $user->id,
            'film_id' => Film::factory()->create(['name' => 'В планах'])->id,
            'status' => 'to-watch',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, ['status' => 'to-watch'])
            ->assertOk()
            ->assertSee('В планах')
            ->assertDontSee('Досмотрен');
    }

    public function test_watchlist_carries_reaction_and_review(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['name' => 'Другой мир']);

        FilmWatcher::factory()->create([
            'watcher_id' => $user->id,
            'film_id' => $film->id,
            'status' => 'watched',
        ]);

        Feedback::query()->create([
            'user_id' => $user->id,
            'film_id' => $film->id,
            'reaction' => 1,
            'text' => 'Кайф',
        ]);

        // Ради этого всё и затевалось: оценка должна быть видна в списке,
        // без похода в get_film за каждым фильмом.
        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, [])
            ->assertOk()
            ->assertSee(['"reaction":1', '"review":"Кайф"']);
    }

    public function test_watchlist_hides_reactions_of_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $film = Film::factory()->create();

        FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $film->id]);

        Feedback::query()->create([
            'user_id' => $other->id,
            'film_id' => $film->id,
            'reaction' => 1,
            'text' => 'Чужой отзыв',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, [])
            ->assertOk()
            ->assertDontSee('Чужой отзыв')
            ->assertSee('"reaction":null');
    }

    public function test_watchlist_tells_neutral_rating_from_no_rating(): void
    {
        $user = User::factory()->create();

        $neutral = Film::factory()->create(['name' => 'Оценён нейтрально']);
        $unrated = Film::factory()->create(['name' => 'Не оценён вовсе']);

        foreach ([$neutral, $unrated] as $film) {
            FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $film->id]);
        }

        // Ноль — поставленная оценка «ни за ни против», а не её отсутствие,
        // поэтому запись в feedback есть и хранит отзыв.
        Feedback::query()->create([
            'user_id' => $user->id,
            'film_id' => $neutral->id,
            'reaction' => 0,
            'text' => 'Ни то ни сё',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, ['reaction' => 0])
            ->assertOk()
            ->assertSee('Оценён нейтрально')
            ->assertDontSee('Не оценён вовсе');

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, ['rated' => false])
            ->assertOk()
            ->assertSee('Не оценён вовсе')
            ->assertDontSee('Оценён нейтрально');
    }

    public function test_watchlist_filters_by_reaction_and_review(): void
    {
        $user = User::factory()->create();

        $liked = Film::factory()->create(['name' => 'Понравился']);
        $disliked = Film::factory()->create(['name' => 'Не понравился']);

        foreach ([$liked, $disliked] as $film) {
            FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $film->id]);
        }

        Feedback::query()->create(['user_id' => $user->id, 'film_id' => $liked->id, 'reaction' => 1, 'text' => 'Отлично']);
        Feedback::query()->create(['user_id' => $user->id, 'film_id' => $disliked->id, 'reaction' => -1]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, ['reaction' => 1])
            ->assertOk()
            ->assertSee('Понравился')
            ->assertDontSee('Не понравился');

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetWatchlist::class, ['has_review' => true])
            ->assertOk()
            ->assertSee('Понравился')
            ->assertDontSee('Не понравился');
    }

    public function test_get_film_distinguishes_neutral_rating_from_none(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetFilm::class, ['id' => $film->id])
            ->assertOk()
            ->assertSee('"reaction":null');

        Feedback::query()->create([
            'user_id' => $user->id,
            'film_id' => $film->id,
            'reaction' => 0,
            'text' => 'Нейтрально',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetFilm::class, ['id' => $film->id])
            ->assertOk()
            ->assertSee('"reaction":0');
    }

    public function test_set_watch_status_adds_and_then_updates(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SetWatchStatus::class, ['film_id' => $film->id, 'status' => 'to-watch'])
            ->assertOk();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SetWatchStatus::class, ['film_id' => $film->id, 'status' => 'watched'])
            ->assertOk();

        // Повторный вызов обязан переписать статус, а не завести вторую строку.
        $this->assertDatabaseCount('film_watchers', 1);
        $this->assertDatabaseHas('film_watchers', [
            'watcher_id' => $user->id,
            'film_id' => $film->id,
            'status' => 'watched',
        ]);
    }

    public function test_set_watch_status_rejects_unknown_status(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SetWatchStatus::class, ['film_id' => $film->id, 'status' => 'посмотрю потом'])
            ->assertHasErrors();

        $this->assertDatabaseCount('film_watchers', 0);
    }

    public function test_remove_from_watchlist_touches_only_own_record(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $film = Film::factory()->create();

        FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $film->id]);
        FilmWatcher::factory()->create(['watcher_id' => $other->id, 'film_id' => $film->id]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(RemoveFromWatchlist::class, ['film_id' => $film->id])
            ->assertOk()
            ->assertSee('"removed":true');

        $this->assertDatabaseMissing('film_watchers', ['watcher_id' => $user->id]);
        $this->assertDatabaseHas('film_watchers', ['watcher_id' => $other->id]);
    }

    // --- оценки и заметки ---

    public function test_set_film_reaction_saves_review(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SetFilmReaction::class, [
                'film_id' => $film->id,
                'reaction' => 1,
                'review' => 'Хорошее кино.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('feedback', [
            'user_id' => $user->id,
            'film_id' => $film->id,
            'reaction' => 1,
            'text' => 'Хорошее кино.',
        ]);
    }

    public function test_zero_reaction_without_review_removes_reaction(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        Feedback::query()->create([
            'user_id' => $user->id,
            'film_id' => $film->id,
            'reaction' => 1,
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SetFilmReaction::class, ['film_id' => $film->id, 'reaction' => 0])
            ->assertOk();

        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_notes_round_trip_through_get_film(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(AddFilmNote::class, ['film_id' => $film->id, 'note' => 'Пересмотреть зимой.'])
            ->assertOk();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetFilm::class, ['id' => $film->id])
            ->assertOk()
            ->assertSee('Пересмотреть зимой.');

        $note = Rating::query()->firstOrFail();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(DeleteFilmNote::class, ['note_id' => $note->id])
            ->assertOk();

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_cannot_delete_someone_elses_note(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $film = Film::factory()->create();

        $note = Rating::query()->create([
            'user_id' => $other->id,
            'film_id' => $film->id,
            'data' => ['comment' => 'Чужое'],
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(DeleteFilmNote::class, ['note_id' => $note->id])
            ->assertHasErrors();

        $this->assertDatabaseCount('ratings', 1);
    }

    public function test_get_film_does_not_leak_other_users_notes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $film = Film::factory()->create();

        Rating::query()->create([
            'user_id' => $other->id,
            'film_id' => $film->id,
            'data' => ['comment' => 'Секрет соседа'],
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetFilm::class, ['id' => $film->id])
            ->assertOk()
            ->assertDontSee('Секрет соседа');
    }

    // --- подборки ---

    public function test_save_collection_creates_hidden_by_default(): void
    {
        $user = User::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SaveCollection::class, ['name' => 'Ночное кино'])
            ->assertOk()
            ->assertSee('Ночное кино');

        $this->assertDatabaseHas('collections', [
            'user_id' => $user->id,
            'name' => 'Ночное кино',
            'visibility' => CollectionVisibility::Hidden->value,
        ]);
    }

    public function test_save_collection_refuses_someone_elses(): void
    {
        $user = User::factory()->create();
        $foreign = Collection::factory()->create(['name' => 'Чужая']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SaveCollection::class, ['id' => $foreign->id, 'name' => 'Перехвачена'])
            ->assertHasErrors();

        $this->assertDatabaseHas('collections', ['id' => $foreign->id, 'name' => 'Чужая']);
    }

    public function test_manage_collection_films_adds_removes_and_renumbers(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $films = Film::factory()->count(3)->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ManageCollectionFilms::class, [
                'collection_id' => $collection->id,
                'action' => 'add',
                'film_ids' => $films->pluck('id')->all(),
            ])
            ->assertOk();

        $this->assertSame([1, 2, 3], CollectionFilm::query()->orderBy('position')->pluck('position')->all());

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ManageCollectionFilms::class, [
                'collection_id' => $collection->id,
                'action' => 'remove',
                'film_ids' => [$films[0]->id],
            ])
            ->assertOk();

        // После удаления позиции обязаны сомкнуться, иначе порядок поедет.
        $this->assertSame([1, 2], CollectionFilm::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_manage_collection_films_reports_unknown_films(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ManageCollectionFilms::class, [
                'collection_id' => $collection->id,
                'action' => 'add',
                'film_ids' => [999999],
            ])
            ->assertOk()
            ->assertSee('unknown_film_ids');
    }

    public function test_manage_collection_films_refuses_someone_elses_collection(): void
    {
        $user = User::factory()->create();
        $foreign = Collection::factory()->create();
        $film = Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ManageCollectionFilms::class, [
                'collection_id' => $foreign->id,
                'action' => 'add',
                'film_ids' => [$film->id],
            ])
            ->assertHasErrors();

        $this->assertDatabaseCount('collection_films', 0);
    }

    public function test_list_collections_scopes_mine_and_public(): void
    {
        $user = User::factory()->create();
        Collection::factory()->create(['user_id' => $user->id, 'name' => 'Моя скрытая']);
        Collection::factory()->public()->create(['name' => 'Чужая публичная']);
        Collection::factory()->create(['name' => 'Чужая скрытая']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ListCollections::class, ['scope' => 'mine'])
            ->assertOk()
            ->assertSee('Моя скрытая')
            ->assertDontSee('Чужая публичная');

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ListCollections::class, ['scope' => 'public'])
            ->assertOk()
            ->assertSee('Чужая публичная')
            ->assertDontSee('Чужая скрытая');
    }

    public function test_get_collection_hides_someone_elses_hidden_collection(): void
    {
        $user = User::factory()->create();
        $foreign = Collection::factory()->create(['name' => 'Совсем скрытая']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetCollection::class, ['id' => $foreign->id])
            ->assertHasErrors();
    }

    public function test_get_collection_shows_public_collection_of_another_user(): void
    {
        $user = User::factory()->create();
        $foreign = Collection::factory()->public()->create(['name' => 'Общая подборка']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetCollection::class, ['id' => $foreign->id])
            ->assertOk()
            ->assertSee('Общая подборка');
    }

    public function test_delete_collection_removes_only_own(): void
    {
        $user = User::factory()->create();
        $mine = Collection::factory()->create(['user_id' => $user->id]);
        $foreign = Collection::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(DeleteCollection::class, ['id' => $foreign->id])
            ->assertHasErrors();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(DeleteCollection::class, ['id' => $mine->id])
            ->assertOk();

        $this->assertDatabaseMissing('collections', ['id' => $mine->id]);
        $this->assertDatabaseHas('collections', ['id' => $foreign->id]);
    }
}
