<?php

namespace Tests\Feature\Policies;

use App\Models\Feedback;
use App\Models\Film;
use App\Models\User;
use App\Policies\FeedbackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FeedbackPolicy();
    }

    // --- create ---

    public function test_user_can_create_feedback_when_no_existing_feedback(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->assertTrue($this->policy->create($user, $film));
    }

    public function test_user_cannot_create_feedback_when_already_has_one(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();
        Feedback::factory()->create(['film_id' => $film->id, 'user_id' => $user->id]);

        $this->assertFalse($this->policy->create($user, $film));
    }

    // --- update ---

    public function test_user_can_update_own_feedback(): void
    {
        $user     = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $feedback));
    }

    public function test_user_cannot_update_others_feedback(): void
    {
        $user     = User::factory()->create();
        $feedback = Feedback::factory()->create();

        $this->assertFalse($this->policy->update($user, $feedback));
    }
}
