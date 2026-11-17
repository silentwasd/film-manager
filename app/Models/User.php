<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasPublicKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPublicKey, Notifiable;

    protected $fillable = [
        'kotonet_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'friend_invite_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];

    public function films(): HasMany
    {
        return $this->hasMany(FilmWatcher::class, 'watcher_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * id друзей — принятая дружба в любом направлении.
     *
     * @return array<int, int>
     */
    public function friendIds(): array
    {
        return Friendship::query()
            ->whereNotNull('accepted_at')
            ->where(fn ($q) => $q->where('user_id', $this->id)->orWhere('friend_id', $this->id))
            ->get(['user_id', 'friend_id'])
            ->map(fn (Friendship $f) => $f->user_id == $this->id ? $f->friend_id : $f->user_id)
            ->values()
            ->all();
    }

    public function isFriendWith(User $other): bool
    {
        return Friendship::query()->between($this->id, $other->id)->whereNotNull('accepted_at')->exists();
    }

    /**
     * Отношение к другому пользователю с точки зрения этого:
     * self, friends, outgoing (я позвал), incoming (меня позвали), none.
     */
    public function friendshipStateWith(User $other): string
    {
        if ($other->id == $this->id) {
            return 'self';
        }

        $friendship = Friendship::query()->between($this->id, $other->id)->first();

        return match (true) {
            $friendship === null => 'none',
            $friendship->isAccepted() => 'friends',
            $friendship->user_id == $this->id => 'outgoing',
            default => 'incoming',
        };
    }

    public function watchPlans(): BelongsToMany
    {
        return $this->belongsToMany(WatchPlan::class, 'watch_plan_user');
    }

    /** Чужие коллекции, добавленные к себе. Только закладка, не копия. */
    public function savedCollections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_saves')->withTimestamps();
    }
}
