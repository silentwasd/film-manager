<?php

namespace Tests\Feature\Management;

use App\Events\UserDataChanged;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FriendTest extends TestCase
{
    use RefreshDatabase;

    private function befriend(User $a, User $b): void
    {
        Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'accepted_at' => now()]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/management/friends')->assertUnauthorized();
    }

    public function test_request_then_accept(): void
    {
        $me = User::factory()->create();
        $him = User::factory()->create(['name' => 'Друг']);

        $this->actingAs($me)->postJson('/api/management/friends', ['user_id' => $him->id])
            ->assertOk()->assertJsonPath('state', 'outgoing');

        $this->actingAs($me)->getJson('/api/management/friends')
            ->assertJsonCount(0, 'friends')
            ->assertJsonPath('outgoing.0.name', 'Друг');

        $this->actingAs($him)->getJson('/api/management/friends')
            ->assertJsonPath('incoming.0.id', $me->id);

        $this->actingAs($him)->postJson("/api/management/friends/{$me->id}/accept")
            ->assertOk()->assertJsonPath('state', 'friends');

        $this->actingAs($me)->getJson('/api/management/friends')
            ->assertJsonPath('friends.0.id', $him->id)
            ->assertJsonCount(0, 'outgoing');

        $this->assertTrue($me->isFriendWith($him));
        $this->assertTrue($him->isFriendWith($me));
    }

    public function test_friend_changes_are_broadcast_to_both(): void
    {
        Event::fake([UserDataChanged::class]);

        $me = User::factory()->create();
        $him = User::factory()->create();

        $this->actingAs($me)->postJson('/api/management/friends', ['user_id' => $him->id]);

        Event::assertDispatched(
            UserDataChanged::class,
            fn (UserDataChanged $e) => $e->topic === 'friends' && in_array($him->id, $e->userIds) && in_array($me->id, $e->userIds)
        );
    }

    public function test_counter_request_makes_friends_immediately(): void
    {
        $me = User::factory()->create();
        $him = User::factory()->create();

        Friendship::create(['user_id' => $him->id, 'friend_id' => $me->id]);

        $this->actingAs($me)->postJson('/api/management/friends', ['user_id' => $him->id])
            ->assertJsonPath('state', 'friends');

        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_cannot_accept_own_outgoing_request(): void
    {
        $me = User::factory()->create();
        $him = User::factory()->create();
        Friendship::create(['user_id' => $me->id, 'friend_id' => $him->id]);

        $this->actingAs($me)->postJson("/api/management/friends/{$him->id}/accept")->assertNotFound();
        $this->assertFalse($me->isFriendWith($him));
    }

    public function test_cannot_befriend_self(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->postJson('/api/management/friends', ['user_id' => $me->id])->assertStatus(422);
    }

    public function test_destroy_removes_friendship_from_both_sides(): void
    {
        $me = User::factory()->create();
        $him = User::factory()->create();
        $this->befriend($him, $me);

        $this->actingAs($me)->deleteJson("/api/management/friends/{$him->id}")
            ->assertOk()->assertJsonPath('state', 'none');

        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_invite_link_makes_friends_without_request(): void
    {
        $inviter = User::factory()->create(['name' => 'Зовущий']);
        $me = User::factory()->create();

        $url = $this->actingAs($inviter)->getJson('/api/management/friends/invite')->assertOk()->json('url');
        $this->assertStringContainsString('/friends/invite/', $url);
        $token = basename($url);

        // Повторный запрос отдаёт ту же ссылку.
        $this->actingAs($inviter)->getJson('/api/management/friends/invite')->assertJsonPath('url', $url);

        $this->actingAs($me)->getJson("/api/management/friends/invite/{$token}")
            ->assertOk()
            ->assertJsonPath('user.name', 'Зовущий')
            ->assertJsonPath('state', 'none');

        $this->actingAs($me)->postJson("/api/management/friends/invite/{$token}")
            ->assertOk()->assertJsonPath('state', 'friends');

        $this->assertTrue($me->isFriendWith($inviter));

        // Повторно — без дублей.
        $this->actingAs($me)->postJson("/api/management/friends/invite/{$token}")->assertOk();
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_invite_accepts_pending_request_in_any_direction(): void
    {
        $inviter = User::factory()->create();
        $me = User::factory()->create();
        Friendship::create(['user_id' => $me->id, 'friend_id' => $inviter->id]);

        $token = basename($this->actingAs($inviter)->getJson('/api/management/friends/invite')->json('url'));

        $this->actingAs($me)->postJson("/api/management/friends/invite/{$token}")->assertOk();

        $this->assertDatabaseCount('friendships', 1);
        $this->assertTrue($me->isFriendWith($inviter));
    }

    public function test_reset_invalidates_old_link(): void
    {
        $inviter = User::factory()->create();
        $me = User::factory()->create();

        $old = basename($this->actingAs($inviter)->getJson('/api/management/friends/invite')->json('url'));
        $new = basename($this->actingAs($inviter)->postJson('/api/management/friends/invite/reset')->json('url'));

        $this->assertNotSame($old, $new);
        $this->actingAs($me)->postJson("/api/management/friends/invite/{$old}")->assertNotFound();
        $this->actingAs($me)->postJson("/api/management/friends/invite/{$new}")->assertOk();
    }

    public function test_own_invite_link_is_rejected(): void
    {
        $me = User::factory()->create();
        $token = basename($this->actingAs($me)->getJson('/api/management/friends/invite')->json('url'));

        $this->actingAs($me)->postJson("/api/management/friends/invite/{$token}")->assertStatus(422);
    }

    public function test_public_profile_reports_friendship_state(): void
    {
        $me = User::factory()->create();
        $him = User::factory()->create();

        $this->getJson("/api/users/{$him->public_key}")->assertJsonMissingPath('data.friendship');

        $this->actingAs($me, 'sanctum')->getJson("/api/users/{$him->public_key}")
            ->assertJsonPath('data.friendship', 'none');

        Friendship::create(['user_id' => $him->id, 'friend_id' => $me->id]);

        $this->actingAs($me, 'sanctum')->getJson("/api/users/{$him->public_key}")
            ->assertJsonPath('data.friendship', 'incoming');

        $this->actingAs($me, 'sanctum')->getJson("/api/users/{$me->public_key}")
            ->assertJsonPath('data.friendship', 'self');
    }

    public function test_invite_token_is_not_leaked(): void
    {
        $him = User::factory()->create(['friend_invite_token' => str_repeat('a', 32)]);

        $this->getJson("/api/users/{$him->public_key}")->assertDontSee(str_repeat('a', 32));
    }
}
