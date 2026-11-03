<?php

namespace App\Actions\AppVersion;

use App\Dtos\AppVersion\AppVersionData;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAppVersionAction
{
    use AsAction;

    /**
     * The running backend version and the newest release of both repos. The frontend compares its own build version.
     */
    public function handle(): AppVersionData
    {
        $backend = FetchLatestReleaseAction::run(config('releases.backend_repo_url'));
        $frontend = FetchLatestReleaseAction::run(config('releases.frontend_repo_url'));

        return new AppVersionData(
            backendVersion: trim((string) file_get_contents(base_path('VERSION'))),
            latestBackendRelease: $backend->release,
            latestFrontendRelease: $frontend->release,
            // The older of the two checks, so the time never claims more freshness than either answer has
            checkedAt: $backend->checkedAt->min($frontend->checkedAt),
        );
    }
}
