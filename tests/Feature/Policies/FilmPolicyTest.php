<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\User;
use App\Policies\FilmPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FilmPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FilmPolicy();
    }

    // --- update ---

    public function test_author_can_update(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $film));
    }

    public function test_non_author_cannot_update(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->assertFalse($this->policy->update($user, $film));
    }

    public function test_admin_can_update_any_film(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $film  = Film::factory()->create();

        $this->assertTrue($this->policy->update($admin, $film));
    }

    // --- delete ---

    public function test_author_can_delete_own_film_without_other_watchers(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $film));
    }

    public function test_author_cannot_delete_film_watched_by_others(): void
    {
        $user    = User::factory()->create();
        $watcher = User::factory()->create();
        $film    = Film::factory()->create(['author_id' => $user->id]);
        FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $watcher->id]);

        $this->assertFalse($this->policy->delete($user, $film));
    }

    public function test_author_can_delete_film_they_themselves_are_watching(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);
        FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $film));
    }

    public function test_non_author_cannot_delete(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->assertFalse($this->policy->delete($user, $film));
    }

    public function test_admin_can_delete_any_film(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $film  = Film::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $film));
    }
}
