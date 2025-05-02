<?php

namespace App\Models;

use App\Enums\FilmCinemaStatus;
use App\Enums\FilmFormat;
use App\Enums\FilmModerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Film extends Model
{
    protected $fillable = [
        'name',
        'original_name',
        'cover',
        'produced_year',
        'release_date',
        'description',
        'format',
        'author_id',
        'moderation_status'
    ];

    protected $casts = [
        'release_date'      => 'immutable_datetime',
        'format'            => FilmFormat::class,
        'cinema_status'     => FilmCinemaStatus::class,
        'moderation_status' => FilmModerationStatus::class
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

    public function download(): BelongsTo
    {
        return $this->belongsTo(Download::class);
    }

    public function audioVariants(): HasMany
    {
        return $this->hasMany(FilmAudioVariant::class);
    }

    public function videoVariants(): HasMany
    {
        return $this->hasMany(FilmVideoVariant::class);
    }

    public function feedbacks(): HasMany
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
}
