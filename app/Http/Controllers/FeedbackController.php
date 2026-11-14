<?php

namespace App\Http\Controllers;

use App\Http\Resources\FeedbackResource;
use App\Models\Feedback;
use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    /**
     * Все отзывы тайтла: на него целиком (`season = null`) и на отдельные сезоны.
     */
    public function index(Film $film)
    {
        return FeedbackResource::collection(
            $film->allFeedbacks()
                 ->with('user')
                 ->latest()
                 ->get()
        );
    }

    public function store(Request $request, Film $film)
    {
        $data = $request->validate([
            'text'     => 'nullable|string|max:512',
            'reaction' => 'nullable|integer|min:-1|max:1',
            'season'   => 'nullable|integer|min:1',
            'create'   => 'nullable|boolean'
        ]);

        $season = $data['season'] ?? null;
        $this->ensureSeasonExists($film, $season);

        if (!($data['create'] ?? false) && $request->user()->cannot('create', [Feedback::class, $film, $season]))
            abort(403);

        $user = $request->user();

        if (!($data['create'] ?? false) && !($data['text'] ?? false) && ($data['reaction'] ?? 0) == 0) {
            abort(400, 'Укажите реакцию или напишите отзыв.');
        } elseif (($data['create'] ?? false) && ($data['reaction'] ?? 0) == 0) {
            $removed = $user->feedbacks()
                            ->where('film_id', $film->id)
                            ->where('season', $season)
                            ->whereNull('text')
                            ->delete();
            if ($removed)
                return;
        }

        $user->feedbacks()->updateOrCreate(
            ['film_id' => $film->id, 'season' => $season],
            collect($data)->except(['create', 'season'])->toArray()
        );
    }

    public function update(Request $request, Film $film, Feedback $feedback)
    {
        if ($request->user()->cannot('update', $feedback))
            abort(403);

        $data = $request->validate([
            'text'     => 'nullable|string|max:512',
            'reaction' => 'nullable|integer|min:-1|max:1',
            'season'   => 'nullable|integer|min:1'
        ]);

        if (!($data['text'] ?? false) && ($data['reaction'] ?? 0) == 0)
            abort(400, 'Укажите реакцию или напишите отзыв.');

        // Отзыв можно перенести на другой сезон (или на сериал целиком),
        // если там у пользователя ещё ничего нет. Поле не пришло — сезон
        // остаётся прежним.
        if ($request->exists('season') && ($data['season'] ?? null) !== $feedback->season) {
            $season = $data['season'] ?? null;
            $this->ensureSeasonExists($film, $season);

            $taken = Feedback::query()
                ->where('user_id', $feedback->user_id)
                ->where('film_id', $feedback->film_id)
                ->where('season', $season)
                ->exists();

            if ($taken)
                throw ValidationException::withMessages([
                    'season' => $season
                        ? "У вас уже есть отзыв на {$season} сезон."
                        : 'У вас уже есть отзыв на весь сериал.'
                ]);
        } else {
            unset($data['season']);
        }

        $feedback->update($data);
    }

    /**
     * Отзыв на сезон возможен, только если у тайтла указано число сезонов
     * и такой сезон в него укладывается.
     */
    protected function ensureSeasonExists(Film $film, ?int $season): void
    {
        if ($season === null)
            return;

        if (!$film->format?->isSerial() || !$film->seasons)
            throw ValidationException::withMessages([
                'season' => 'У этого тайтла не указаны сезоны.'
            ]);

        if ($season > $film->seasons)
            throw ValidationException::withMessages([
                'season' => "Сезонов у тайтла: {$film->seasons}."
            ]);
    }
}
