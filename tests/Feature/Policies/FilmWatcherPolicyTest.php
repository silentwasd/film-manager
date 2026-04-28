<?php

namespace Tests\Feature\Policies;

use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\User;
use App\Policies\FilmWatcherPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmWatcherPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FilmWatcherPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FilmWatcherPolicy();
    }

    // --- update ---

    public function test_owner_can_update(): void
    {
        $user    = User::factory()->create();
        $watcher = FilmWatcher::factory()->create(['watcher_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $watcher));
    }

    public function test_non_owner_cannot_update(): void
    {
        $user    = User::factory()->create();
        $watcher = FilmWatcher::factory()->create();

        $this->assertFalse($this->policy->update($user, $watcher));
    }

    // --- delete ---

    public function test_owner_can_delete(): void
    {
        $user    = User::factory()->create();
        $watcher = FilmWatcher::factory()->create(['watcher_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $watcher));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $user    = User::factory()->create();
        $watcher = FilmWatcher::factory()->create();

        $this->assertFalse($this->policy->delete($user, $watcher));
    }
}
