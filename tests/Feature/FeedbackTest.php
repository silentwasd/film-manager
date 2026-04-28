<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_feedback_for_film(): void
    {
        $film = Film::factory()->create();
        Feedback::factory()->create(['film_id' => $film->id, 'reaction' => 1]);

        $this->getJson("/api/films/{$film->id}/feedback")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    public function test_index_is_public(): void
    {
        $film = Film::factory()->create();

        $this->getJson("/api/films/{$film->id}/feedback")->assertOk();
    }

    public function test_store_creates_feedback_with_reaction(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => 1])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'user_id' => $user->id, 'reaction' => 1]);
    }

    public function test_store_creates_feedback_with_text(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", [
                 'text'   => 'Great film!',
                 'create' => true,
             ])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'text' => 'Great film!']);
    }

    public function test_store_requires_authentication(): void
    {
        $film = Film::factory()->create();

        $this->postJson("/api/films/{$film->id}/feedback", ['reaction' => 1])
             ->assertUnauthorized();
    }

    public function test_store_fails_without_reaction_or_text(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => 0])
             ->assertBadRequest();
    }

    public function test_store_prevents_duplicate_feedback_without_create_flag(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'reaction' => 1]);

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => -1])
             ->assertForbidden();
    }

    public function test_store_with_create_flag_upserts_feedback(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'reaction' => 1]);

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => -1, 'create' => true])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'user_id' => $user->id, 'reaction' => -1]);
    }

    public function test_update_changes_feedback(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();
        $feedback = Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'reaction' => 1]);

        $this->actingAs($user)
             ->putJson("/api/films/{$film->id}/feedback/{$feedback->id}", ['reaction' => -1])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['id' => $feedback->id, 'reaction' => -1]);
    }

    public function test_update_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $film = Film::factory()->create();
        $feedback = Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $owner->id, 'reaction' => 1]);

        $this->actingAs($other)
             ->putJson("/api/films/{$film->id}/feedback/{$feedback->id}", ['reaction' => -1])
             ->assertForbidden();
    }

    public function test_update_requires_authentication(): void
    {
        $film = Film::factory()->create();
        $feedback = Feedback::factory()->create(['film_id' => $film->id, 'reaction' => 1]);

        $this->putJson("/api/films/{$film->id}/feedback/{$feedback->id}", ['reaction' => -1])
             ->assertUnauthorized();
    }

    public function test_update_fails_without_reaction_or_text(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();
        $feedback = Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'reaction' => 1]);

        $this->actingAs($user)
             ->putJson("/api/films/{$film->id}/feedback/{$feedback->id}", ['reaction' => 0])
             ->assertBadRequest();
    }
}
