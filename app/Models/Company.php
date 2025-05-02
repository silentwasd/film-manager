<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'description',
        'link',
        'author_id'
    ];

    public function films(): BelongsToMany
    {
        return $this->belongsToMany(Film::class)
                    ->orderByDesc('produced_year')
                    ->orderBy('id');
    }
}
