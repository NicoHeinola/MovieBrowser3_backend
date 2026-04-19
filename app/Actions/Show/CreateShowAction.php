<?php

namespace App\Actions\Show;

use App\Dtos\Show\CreateShowData;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowAction
{
    use AsAction;

    public function handle(CreateShowData $data): Show
    {
        return Show::create([
            'banner_url' => $data->bannerUrl,
            'card_image_url' => $data->cardImageUrl,
            'preview_url' => $data->previewUrl,
            'description' => $data->description,
        ]);
    }
}
