<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\Film;
use App\Models\User;

class FeedbackPolicy
{
    /**
     * Один отзыв на тайтл целиком (`$season = null`) и по одному на каждый сезон.
     */
    public function create(User $user, Film $film, ?int $season = null): bool
    {
        return !$user->feedbacks()
                     ->where('film_id', $film->id)
                     ->where('season', $season)
                     ->exists();
    }

    public function update(User $user, Feedback $feedback): bool
    {
        return $feedback->user_id == $user->id;
    }
}
