<?php

namespace App\Enums;

/**
 * Why a setup value failed its check. The frontend turns each code into translated text.
 */
enum SetupCheckCode: string
{
    case PathRequired = 'path_required';
    case PathNotAbsolute = 'path_not_absolute';
    case PathHasParentSegment = 'path_has_parent_segment';
    case PathOutsideMediaFolders = 'path_outside_media_folders';
    case PathIsFile = 'path_is_file';
    case PathNotWritable = 'path_not_writable';
    case PathNotCreatable = 'path_not_creatable';
    case VlcNotFound = 'vlc_not_found';
    case VlcWrongBinary = 'vlc_wrong_binary';
    case VlcBridgeUnreachable = 'vlc_bridge_unreachable';
    case VlcBridgeOutdated = 'vlc_bridge_outdated';
    case VlcBridgeUnauthorized = 'vlc_bridge_unauthorized';
    case VlcBridgeRejected = 'vlc_bridge_rejected';
}
