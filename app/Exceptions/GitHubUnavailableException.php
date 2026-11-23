<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * GitHub didn't answer a release check, or answered without a usable release. Offline installs and rate limits are
 * normal, so it isn't reported; the service logs why. The release check caches it as "no release found".
 */
class GitHubUnavailableException extends RuntimeException implements ShouldntReport {}
