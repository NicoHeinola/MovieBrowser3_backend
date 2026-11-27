<?php

namespace App\Enums;

use App\Enums\Concerns\HasNekoBtNumber;

/**
 * nekoBT's video codecs.
 */
enum NekoBtVideoCodec: string
{
    use HasNekoBtNumber;

    case H264 = 'h264';
    case H265 = 'h265';
    case Av1 = 'av1';
    case Vp9 = 'vp9';
    case Mpeg2 = 'mpeg2';
    case Xvid = 'xvid';
    case Wmv = 'wmv';
    case Vc1 = 'vc1';
    case Other = 'other';

    public function getNekoBtNumber(): int
    {
        return match ($this) {
            self::Other => 0,
            self::H264 => 1,
            self::H265 => 2,
            self::Av1 => 3,
            self::Vp9 => 4,
            self::Mpeg2 => 5,
            self::Xvid => 6,
            self::Wmv => 7,
            self::Vc1 => 8,
        };
    }
}
