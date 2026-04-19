<?php

namespace App\Actions\ShowLink;

use App\Dtos\ShowLink\CreateShowLinkData;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowLinkAction
{
    use AsAction;

    public function handle(Show $show, CreateShowLinkData $data): ShowLink
    {
        return $show->outgoingLinks()->create([
            'target_show_id' => $data->targetShowId,
            'type' => $data->type,
        ]);
    }
}
