<?php

namespace App\Enums;

/**
 * nekoBT's video types: where a torrent's video comes from and how it was processed.
 */
enum NekoBtVideoSource: string
{
    case Hybrid = 'hybrid';
    case BdRemux = 'bd_remux';
    case BdEncode = 'bd_encode';
    case BdMini = 'bd_mini';
    case BdDisc = 'bd_disc';
    case WebDl = 'web_dl';
    case WebEncode = 'web_encode';
    case WebMini = 'web_mini';
    case DvdEncode = 'dvd_encode';
    case DvdRemux = 'dvd_remux';
    case DvdDisc = 'dvd_disc';
    case TvRaw = 'tv_raw';
    case TvEncode = 'tv_encode';
    case LaserDisc = 'laser_disc';
    case Vhs = 'vhs';
    case Other = 'other';
}
