<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A torrent site didn't answer or answered with an error. Sites going down is normal, so it isn't reported; the
 * service logs what the site answered.
 */
class TorrentSiteUnavailableException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  string  $site  The site's code, such as `nekobt`, telling the frontend which site failed
     */
    public function __construct(public readonly string $site)
    {
        parent::__construct("The torrent site {$site} didn't answer.");
    }

    public function render(): JsonResponse
    {
        return ApiErrorDetail::response(ApiErrorCode::TorrentSiteUnavailable, 502, ['site' => $this->site]);
    }
}
