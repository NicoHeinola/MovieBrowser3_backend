<?php

namespace App\Enums;

/**
 * nekoBT's levels: how much a release group changed an official release, from untouched to a fansub of its own.
 */
enum NekoBtLevel: string
{
    case Official = 'official';
    case SlightModifications = 'slight_modifications';
    case SmallScaleFansub = 'small_scale_fansub';
    case FullScaleFansub = 'full_scale_fansub';
}
