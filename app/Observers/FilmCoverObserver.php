<?php

namespace App\Observers;

use App\Models\Film;
use App\Services\Images\CoverThumbnail;

/**
 * Держит превью в паре с постером, откуда бы постер ни пришёл: форма,
 * импорт с Кинопоиска и Шикимори, перенос на прод.
 */
class FilmCoverObserver
{
    public function __construct(private CoverThumbnail $thumbnails)
    {
    }

    public function saving(Film $film): void
    {
        // Сменился постер, а превью вместе с ним не передали (перенос на прод
        // приносит готовое) — режем заново. Без постера нет и превью.
        if ($film->isDirty('cover') && !$film->isDirty('cover_thumb'))
            $film->cover_thumb = $this->thumbnails->make($film->cover);
    }
}
