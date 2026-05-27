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

    public function reciprocalType(): ?self
    {
        return match ($this) {
            self::Sequel => self::Prequel,
            self::Prequel => self::Sequel,
            self::SuggestedNext => self::SuggestedPrevious,
            self::SuggestedPrevious => self::SuggestedNext,
            default => null,
        };
    }
}
