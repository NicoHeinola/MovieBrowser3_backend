<?php

namespace App\Actions\ShowLink;

use App\Actions\ShowLink\Concerns\InteractsWithReciprocalShowLinks;
use App\Dtos\ShowLink\ReciprocalShowLinkData;
use App\Dtos\ShowLink\UpdateShowLinkData;
use App\Models\ShowLink\ShowLink;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowLinkAction
{
    use AsAction;
    use InteractsWithReciprocalShowLinks;

    public function handle(UpdateShowLinkData $data): ShowLink
    {
        return DB::transaction(function () use ($data): ShowLink {
            $showLink = $data->showLink;
            $originalReciprocalLink = $this->findReciprocalShowLink($showLink);

            $showLink->fill(Arr::only($data->all(), $showLink->getFillable()));
            $showLink->save();

            $updatedShowLink = $showLink->fresh();
            $updatedReciprocalData = $this->reciprocalDataFor($updatedShowLink);

            if ($originalReciprocalLink !== null && !$this->matchesReciprocalData($originalReciprocalLink, $updatedReciprocalData)) {
                $originalReciprocalLink->delete();
            }

            $this->createReciprocalShowLink($updatedShowLink);

            return $updatedShowLink;
        });
    }

    private function matchesReciprocalData(ShowLink $showLink, ?ReciprocalShowLinkData $reciprocalLinkData): bool
    {
        if ($reciprocalLinkData === null) {
            return false;
        }

        return $reciprocalLinkData->matches($showLink);
    }
}
