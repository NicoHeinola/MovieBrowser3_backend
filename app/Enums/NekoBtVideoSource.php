<?php

namespace App\Enums;

use App\Enums\Concerns\HasNekoBtNumber;

/**
 * nekoBT's video types: where a torrent's video comes from and how it was processed.
 */
enum NekoBtVideoSource: string
{
    use HasNekoBtNumber;

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

    public function getNekoBtNumber(): int
    {
        return match ($this) {
            self::Other => 0,
            self::Vhs => 1,
            self::LaserDisc => 2,
            self::TvEncode => 3,
            self::TvRaw => 4,
            self::DvdRemux => 5,
            self::DvdEncode => 6,
            self::WebMini => 7,
            self::WebEncode => 8,
            self::WebDl => 9,
            self::BdDisc => 11,
            self::BdMini => 12,
            self::BdEncode => 13,
            self::BdRemux => 14,
            self::Hybrid => 15,
            self::DvdDisc => 16,
        };
    }
}
