<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * A torrent site didn't answer or answered with an error. Sites going down is normal, so it isn't reported; the
 * service logs what the site answered.
 */
class TorrentSiteUnavailableException extends ApiException implements ShouldntReport
{
    /**
     * @param  string  $site  The site's code, such as `nekobt`, telling the frontend which site failed
     */
    public function __construct(public readonly string $site)
    {
        parent::__construct(ApiErrorCode::TorrentSiteUnavailable, 502, "The torrent site {$site} didn't answer.", ['site' => $site]);
    }
}
