<?php

namespace App\Models;

use App\Enums\WatchPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WatchPlan extends Model
{
    protected $fillable = [
        'film_id',
        'created_by',
        'status',
        'position',
        'note',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => WatchPlanStatus::class,
        'started_at' => 'date',
        'finished_at' => 'date',
    ];

    protected $attributes = [
        'status' => 'planned',
    ];

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'watch_plan_user');
    }

    public function scopeWithParticipant(Builder $query, int $userId): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->whereKey($userId));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /** Следующая позиция в конце общей очереди. */
    public static function nextPosition(): int
    {
        return ((int) static::query()->max('position')) + 1;
    }
}
