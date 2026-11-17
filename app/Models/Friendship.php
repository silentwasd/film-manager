<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Одна строка на пару. user_id позвал, friend_id позвали; accepted_at пуст —
 * заявка висит, заполнен — друзья в обе стороны.
 */
class Friendship extends Model
{
    protected $fillable = [
        'user_id',
        'friend_id',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    /** Строка этой пары в любом направлении. */
    public function scopeBetween(Builder $query, int $a, int $b): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('user_id', $a)->where('friend_id', $b))
            ->orWhere(fn (Builder $q) => $q->where('user_id', $b)->where('friend_id', $a))
        );
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
