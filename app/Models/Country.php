<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name'
    ];

    public function films(): BelongsToMany
    {
        return $this->belongsToMany(Film::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
