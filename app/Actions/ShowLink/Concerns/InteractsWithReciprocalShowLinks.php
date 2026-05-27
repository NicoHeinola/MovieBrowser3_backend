<?php

namespace App\Actions\ShowLink\Concerns;

use App\Dtos\ShowLink\ReciprocalShowLinkData;
use App\Enums\ShowLinkType;
use App\Models\ShowLink\ShowLink;

trait InteractsWithReciprocalShowLinks
{
    protected function createReciprocalShowLink(ShowLink $showLink): void
    {
        $reciprocalLinkData = $this->reciprocalDataFor($showLink);

        if ($reciprocalLinkData === null) {
            return;
        }

        ShowLink::query()->firstOrCreate($reciprocalLinkData->toArray());
    }

    protected function deleteReciprocalShowLink(ShowLink $showLink): void
    {
        $this->findReciprocalShowLink($showLink)?->delete();
    }

    protected function findReciprocalShowLink(ShowLink $showLink): ?ShowLink
    {
        $reciprocalLinkData = $this->reciprocalDataFor($showLink);

        if ($reciprocalLinkData === null) {
            return null;
        }

        return ShowLink::query()->where($reciprocalLinkData->toArray())->first();
    }

    protected function reciprocalDataFor(ShowLink $showLink): ?ReciprocalShowLinkData
    {
        return $this->reciprocalData(
            $showLink->source_show_id,
            $showLink->target_show_id,
            $showLink->type,
        );
    }

    protected function reciprocalData(int $sourceShowId, int $targetShowId, ShowLinkType $type): ?ReciprocalShowLinkData
    {
        $reciprocalType = $type->reciprocalType();

        if ($reciprocalType === null) {
            return null;
        }

        return new ReciprocalShowLinkData(
            sourceShowId: $targetShowId,
            targetShowId: $sourceShowId,
            type: $reciprocalType,
        );
    }
}
