<?php

namespace App\Enums;

use App\Enums\Concerns\HasNekoBtNumber;

/**
 * nekoBT's levels: how much a release group changed an official release, from untouched to a fansub of its own.
 */
enum NekoBtLevel: string
{
    use HasNekoBtNumber;

    case Official = 'official';
    case SlightModifications = 'slight_modifications';
    case SmallScaleFansub = 'small_scale_fansub';
    case FullScaleFansub = 'full_scale_fansub';

    public function getNekoBtNumber(): int
    {
        return match ($this) {
            self::Official => 0,
            self::SlightModifications => 1,
            self::SmallScaleFansub => 2,
            self::FullScaleFansub => 3,
        };
    }
}
