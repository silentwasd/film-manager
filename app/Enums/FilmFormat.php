<?php

namespace App\Enums;

enum FilmFormat: string
{
    case Film          = 'film';
    case MiniSeries    = 'mini-series';
    case Series        = 'series';
    case Cartoon       = 'cartoon';
    case CartoonSeries = 'cartoon-series';

    /**
     * Многосерийные форматы — у них бывают сезоны.
     */
    public function isSerial(): bool
    {
        return in_array($this, [self::MiniSeries, self::Series, self::CartoonSeries], true);
    }
}
