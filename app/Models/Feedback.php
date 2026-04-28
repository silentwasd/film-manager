<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;
    protected $fillable = [
        'film_id',
        'user_id',
        'text',
        'reaction'
    ];

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
