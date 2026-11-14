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

    private function series(int $seasons = 3): Film
    {
        return Film::factory()->create(['format' => 'series', 'seasons' => $seasons]);
    }

    public function test_store_creates_feedback_for_season(): void
    {
        $user = User::factory()->create();
        $film = $this->series();

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['text' => 'Первый сезон огонь', 'reaction' => 1, 'season' => 1])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'user_id' => $user->id, 'season' => 1]);
    }

    public function test_season_and_series_feedback_coexist(): void
    {
        $user = User::factory()->create();
        $film = $this->series();

        foreach ([1, 2, null] as $season) {
            $this->actingAs($user)
                 ->postJson("/api/films/{$film->id}/feedback", ['text' => 'Отзыв', 'reaction' => 1, 'season' => $season])
                 ->assertOk();
        }

        $this->assertDatabaseCount('feedback', 3);

        $this->getJson("/api/films/{$film->id}/feedback")
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    public function test_store_prevents_duplicate_feedback_for_same_season(): void
    {
        $user = User::factory()->create();
        $film = $this->series();
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => 2, 'reaction' => 1]);

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => -1, 'season' => 2])
             ->assertForbidden();
    }

    public function test_series_feedback_allowed_when_only_season_feedback_exists(): void
    {
        $user = User::factory()->create();
        $film = $this->series();
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => 2, 'reaction' => 1]);

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => -1])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'season' => null, 'reaction' => -1]);
        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'season' => 2, 'reaction' => 1]);
    }

    public function test_store_rejects_season_beyond_count(): void
    {
        $user = User::factory()->create();
        $film = $this->series(2);

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => 1, 'season' => 3])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('season');
    }

    public function test_store_rejects_season_for_film_without_seasons(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['format' => 'film']);

        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => 1, 'season' => 1])
             ->assertUnprocessable();
    }

    public function test_quick_reaction_does_not_touch_season_feedback(): void
    {
        $user = User::factory()->create();
        $film = $this->series();
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => 1, 'reaction' => 1]);

        // Кнопки «понравилось / нет» в «Моих фильмах» — про сериал целиком.
        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => -1, 'create' => true])
             ->assertOk();
        $this->actingAs($user)
             ->postJson("/api/films/{$film->id}/feedback", ['reaction' => 0, 'create' => true])
             ->assertOk();

        $this->assertDatabaseCount('feedback', 1);
        $this->assertDatabaseHas('feedback', ['film_id' => $film->id, 'season' => 1, 'reaction' => 1]);
    }

    public function test_update_moves_feedback_to_another_season(): void
    {
        $user = User::factory()->create();
        $film = $this->series();
        $feedback = Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => 1, 'reaction' => 1]);

        $this->actingAs($user)
             ->putJson("/api/films/{$film->id}/feedback/{$feedback->id}", ['reaction' => 1, 'season' => 2])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['id' => $feedback->id, 'season' => 2]);
    }

    public function test_update_refuses_to_move_onto_taken_season(): void
    {
        $user = User::factory()->create();
        $film = $this->series();
        $first = Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => 1, 'reaction' => 1]);
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => null, 'reaction' => 1]);

        $this->actingAs($user)
             ->putJson("/api/films/{$film->id}/feedback/{$first->id}", ['reaction' => 1, 'season' => null])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('season');

        $this->assertDatabaseHas('feedback', ['id' => $first->id, 'season' => 1]);
    }

    public function test_update_without_season_keeps_it(): void
    {
        $user = User::factory()->create();
        $film = $this->series();
        $feedback = Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id, 'season' => 3, 'reaction' => 1]);

        $this->actingAs($user)
             ->putJson("/api/films/{$film->id}/feedback/{$feedback->id}", ['reaction' => -1])
             ->assertOk();

        $this->assertDatabaseHas('feedback', ['id' => $feedback->id, 'season' => 3, 'reaction' => -1]);
    }

    public function test_film_update_clears_seasons_for_non_serial_format(): void
    {
        $author = User::factory()->create();
        $film = Film::factory()->create(['format' => 'series', 'seasons' => 4, 'author_id' => $author->id]);

        $this->actingAs($author)
             ->putJson("/api/management/films/{$film->id}", ['name' => $film->name, 'format' => 'series', 'seasons' => 5])
             ->assertOk();
        $this->assertSame(5, $film->fresh()->seasons);

        $this->actingAs($author)
             ->putJson("/api/management/films/{$film->id}", ['name' => $film->name, 'format' => 'film', 'seasons' => 5])
             ->assertOk();
        $this->assertNull($film->fresh()->seasons);
    }
}
