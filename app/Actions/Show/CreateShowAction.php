<?php

namespace App\Actions\Show;

use App\Actions\ShowTitle\CreateShowTitleAction;
use App\Dtos\Show\CreateShowData;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowAction
{
    use AsAction;

    public function __construct(private readonly CreateShowTitleAction $createShowTitleAction) {}

    public function handle(CreateShowData $data): Show
    {
        return DB::transaction(function () use ($data): Show {
            $show = Show::create([
                'banner_url' => $data->bannerUrl,
                'card_image_url' => $data->cardImageUrl,
                'preview_url' => $data->previewUrl,
                'description' => $data->description,
            ]);

            foreach ($data->titles as $title) {
                $this->createShowTitleAction->handle($show, $title->title, $title->isPrimary);
            }

            return $show->load('titles');
        });
    }
}
