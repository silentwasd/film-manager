<?php

namespace App\Enums;

enum FilmModerationStatus: string
{
    case Draft  = 'draft';
    case OnReview  = 'on-review';
    case Published = 'published';
}
