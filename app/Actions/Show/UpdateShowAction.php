<?php

namespace App\Actions\Show;

use App\Actions\ShowTitle\SyncShowTitlesAction;
use App\Dtos\Show\UpdateShowData;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateShowAction
{
    use AsAction;

    public function __construct(private readonly SyncShowTitlesAction $syncShowTitlesAction) {}

    public function handle(UpdateShowData $data): Show
    {
        return DB::transaction(function () use ($data): Show {
            $attributes = [];

            if (!($data->bannerUrl instanceof Optional)) {
                $attributes['banner_url'] = $data->bannerUrl;
            }

            if (!($data->cardImageUrl instanceof Optional)) {
                $attributes['card_image_url'] = $data->cardImageUrl;
            }

            if (!($data->previewUrl instanceof Optional)) {
                $attributes['preview_url'] = $data->previewUrl;
            }

            if (!($data->description instanceof Optional)) {
                $attributes['description'] = $data->description;
            }

            if ($attributes !== []) {
                $data->show->update($attributes);
            }

            if (!($data->titles instanceof Optional)) {
                $this->syncShowTitlesAction->handle($data->show, $data->titles);
            }

            return $data->show->fresh()->load('titles');
        });
    }
}
