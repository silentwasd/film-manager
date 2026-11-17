<?php

namespace App\Enums;

enum WatchPlanStatus: string
{
    /** Очередь: порядок задаётся перетаскиванием. */
    case Planned = 'planned';

    /** Начали — с какого дня, видно по started_at. */
    case Watching = 'watching';

    /** Досмотрели — всем участникам в «Моих фильмах» стоит «Просмотрено». */
    case Watched = 'watched';
}
