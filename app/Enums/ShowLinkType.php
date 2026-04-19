<?php

namespace App\Enums;

enum ShowLinkType: string
{
    case Sequel = 'sequel';
    case Prequel = 'prequel';
    case TvSpecial = 'tv_special';
    case SuggestedNext = 'suggested_next';
    case SuggestedPrevious = 'suggested_previous';
    case SpinOff = 'spin_off';
}
