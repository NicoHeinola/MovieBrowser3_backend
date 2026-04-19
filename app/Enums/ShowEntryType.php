<?php

namespace App\Enums;

enum ShowEntryType: string
{
    case Season = 'season';
    case TvSpecial = 'tv_special';
    case Movie = 'movie';
}
