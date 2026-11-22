<?php

namespace App\Enums;

/**
 * nekoBT's video codecs.
 */
enum NekoBtVideoCodec: string
{
    case H264 = 'h264';
    case H265 = 'h265';
    case Av1 = 'av1';
    case Vp9 = 'vp9';
    case Mpeg2 = 'mpeg2';
    case Xvid = 'xvid';
    case Wmv = 'wmv';
    case Vc1 = 'vc1';
    case Other = 'other';
}
