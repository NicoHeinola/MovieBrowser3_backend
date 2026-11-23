<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A video doesn't fit on the disk it's copied to (with `CopyVideoFileAction::RESERVED_DISK_BYTES` left over), so the
 * copy wasn't started. Its own class, so a caller can tell a full disk from a failed copy.
 */
class NotEnoughDiskSpaceException extends RuntimeException {}
