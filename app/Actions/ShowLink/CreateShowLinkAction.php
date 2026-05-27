<?php

namespace App\Actions\ShowLink;

use App\Actions\ShowLink\Concerns\InteractsWithReciprocalShowLinks;
use App\Dtos\ShowLink\CreateShowLinkData;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowLinkAction
{
    use AsAction;
    use InteractsWithReciprocalShowLinks;

    public function handle(Show $show, CreateShowLinkData $data): ShowLink
    {
        return DB::transaction(function () use ($show, $data): ShowLink {
            $showLink = $show->outgoingLinks()->create([
                'target_show_id' => $data->targetShowId,
                'type' => $data->type,
            ]);

            $this->createReciprocalShowLink($showLink);

            return $showLink->fresh();
        });
    }
}
