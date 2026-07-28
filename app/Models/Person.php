<?php

namespace App\Models;

use App\Enums\PersonSex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'original_name',
        'birth_date',
        'death_date',
        'sex',
        'photo',
        'author_id',
        'country_id',
        'shikimori_id'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'sex'        => PersonSex::class
    ];

    public function films(): HasMany
    {
        return $this->hasMany(FilmPerson::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
