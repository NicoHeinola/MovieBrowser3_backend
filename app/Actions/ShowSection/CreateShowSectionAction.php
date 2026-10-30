<?php

namespace App\Actions\ShowSection;

use App\Dtos\ShowSection\CreateShowSectionData;
use App\Models\ShowSection\ShowSection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowSectionAction
{
    use AsAction;

    public function handle(CreateShowSectionData $data): ShowSection
    {
        return DB::transaction(function () use ($data): ShowSection {
            return ShowSection::query()->create([
                'category_id' => $data->categoryId,
                'description' => $data->description,
                'sort_order' => $data->sortOrder,
            ]);
        });
    }
}
