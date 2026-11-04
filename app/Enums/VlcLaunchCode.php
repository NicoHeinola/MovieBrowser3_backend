<?php

namespace App\Enums;

/**
 * Why VLC could not be opened, for failures the setup check can't hit (those are in SetupCheckCode). The frontend
 * turns each code into translated text.
 */
enum VlcLaunchCode: string
{
    case VlcClosedEarly = 'vlc_closed_early';
    case VlcStartFailed = 'vlc_start_failed';
    case VlcStartTimeout = 'vlc_start_timeout';
    case VlcNoFreePort = 'vlc_no_free_port';
    case VlcPathConversionFailed = 'vlc_path_conversion_failed';
}
