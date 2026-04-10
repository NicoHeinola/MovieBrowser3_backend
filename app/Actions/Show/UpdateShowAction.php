<?php

namespace App\Actions\Show;

use App\Actions\ShowTitle\SyncShowTitlesAction;
use App\Dtos\Show\UpdateShowData;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowAction
{
    use AsAction;

    public function __construct(private readonly SyncShowTitlesAction $syncShowTitlesAction) {}

    public function handle(UpdateShowData $data): Show
    {
        return DB::transaction(function () use ($data): Show {
            $attributes = [];

            if ($data->hasProperty('bannerUrl')) {
                $attributes['banner_url'] = $data->bannerUrl;
            }

            if ($data->hasProperty('cardImageUrl')) {
                $attributes['card_image_url'] = $data->cardImageUrl;
            }

            if ($data->hasProperty('previewUrl')) {
                $attributes['preview_url'] = $data->previewUrl;
            }

            if ($attributes !== []) {
                $data->show->update($attributes);
            }

            if ($data->hasProperty('titles')) {
                $this->syncShowTitlesAction->handle($data->show, $data->titles);
            }

            return $data->show->fresh()->load('titles');
        });
    }
}
