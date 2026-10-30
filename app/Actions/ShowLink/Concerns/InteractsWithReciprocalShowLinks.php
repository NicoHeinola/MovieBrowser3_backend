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
        $reciprocalLink = $this->findReciprocalShowLink($showLink);

        if ($reciprocalLink !== null) {
            $this->deleteReciprocalUnlessStillImplied($reciprocalLink, $showLink);
        }
    }

    /**
     * Several types share a reciprocal (sequel, spin-off and TV special all imply a prequel back),
     * so keep the reciprocal while another link between the same shows still implies it.
     *
     * Example: Breaking Bad → Better Call Saul as `sequel` creates Better Call Saul → Breaking Bad `prequel`.
     * Adding Breaking Bad → Better Call Saul as `spin_off` reuses that same `prequel` row.
     * Deleting the `spin_off` must keep the `prequel`, because the `sequel` still implies it;
     * only once the `sequel` is gone too is the `prequel` deleted.
     */
    protected function deleteReciprocalUnlessStillImplied(ShowLink $reciprocalLink, ShowLink $showLink): void
    {
        $implyingTypes = array_values(array_filter(
            ShowLinkType::cases(),
            fn (ShowLinkType $type): bool => $type->reciprocalType() === $reciprocalLink->type,
        ));

        $isStillImplied = ShowLink::query()
            ->whereKeyNot($showLink->getKey())
            ->where('source_show_id', $reciprocalLink->target_show_id)
            ->where('target_show_id', $reciprocalLink->source_show_id)
            ->whereIn('type', $implyingTypes)
            ->exists();

        if (!$isStillImplied) {
            $reciprocalLink->delete();
        }
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
