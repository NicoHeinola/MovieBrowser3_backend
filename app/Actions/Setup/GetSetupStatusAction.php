<?php

namespace App\Actions\Setup;

use App\Actions\EpisodePlayback\CheckVlcBinaryAction;
use App\Dtos\Setup\SetupStatusData;
use App\Models\Setting\Setting;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSetupStatusAction
{
    use AsAction;

    public function __construct(
        private readonly CheckVlcBinaryAction $checkVlcBinaryAction,
    ) {}

    public function handle(): SetupStatusData
    {
        $completedAt = Setting::query()->whereKey('setup_completed_at')->value('value');

        return new SetupStatusData(
            $completedAt ? Carbon::parse($completedAt) : null,
            config('deploy.media_folders'),
            $this->detectVlc(),
        );
    }

    private function detectVlc(): ?string
    {
        foreach (config('vlc.detect_paths') as $candidate) {
            if ($this->checkVlcBinaryAction->handle($candidate) === null) {
                return $candidate;
            }
        }

        return null;
    }
}
