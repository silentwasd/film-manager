<?php

namespace App\Models;

use App\Enums\FilmCinemaStatus;
use App\Enums\FilmFormat;
use App\Enums\FilmModerationStatus;
use App\Observers\FilmCoverObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(FilmCoverObserver::class)]
class Film extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'original_name',
        'cover',
        'cover_thumb',
        'background_cover',
        'produced_year',
        'release_date',
        'description',
        'format',
        'seasons',
        'author_id',
        'moderation_status',
        'shikimori_id',
        'kinopoisk_id',
    ];

    protected $casts = [
        'release_date' => 'immutable_datetime',
        'format' => FilmFormat::class,
        'seasons' => 'integer',
        'cinema_status' => FilmCinemaStatus::class,
        'moderation_status' => FilmModerationStatus::class,
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(FilmWatcher::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(FilmPerson::class);
    }

    /**
     * Отзывы на тайтл целиком — без отзывов на отдельные сезоны. Именно они
     * считаются в лайках, топе главной и оценке в «Моих фильмах».
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class)->whereNull('season');
    }

    /**
     * Все отзывы: на тайтл целиком и на сезоны.
     */
    public function allFeedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function franchises(): BelongsToMany
    {
        return $this->belongsToMany(Franchise::class);
    }
}
