<?php

namespace App\Actions\Setup;

use App\Actions\EpisodePlayback\CheckVlcCanStartAction;
use App\Dtos\Setup\SetupStatusData;
use App\Enums\SettingKey;
use App\Models\Setting\Setting;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSetupStatusAction
{
    use AsAction;

    public function __construct(
        private readonly CheckVlcCanStartAction $checkVlcCanStartAction,
    ) {}

    public function handle(): SetupStatusData
    {
        $completedAt = Setting::query()->whereKey(SettingKey::SetupCompletedAt->value)->value('value');

        return new SetupStatusData(
            $completedAt ? Carbon::parse($completedAt) : null,
            config('deploy.media_folders'),
            $this->detectVlc(),
        );
    }

    private function detectVlc(): ?string
    {
        foreach (config('vlc.detect_paths') as $candidate) {
            if ($this->checkVlcCanStartAction->handle($candidate)->ok) {
                return $candidate;
            }
        }

        return null;
    }
}
