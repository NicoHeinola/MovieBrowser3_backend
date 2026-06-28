<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use App\Models\Show\Show;
use Illuminate\Database\Seeder;

class ShowCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        Show::query()->each(function (Show $show) use ($categoryIds): void {
            $attachCount = rand(1, min(3, $categoryIds->count()));
            $selectedCategoryIds = $categoryIds->random($attachCount)->all();

            $show->categories()->syncWithoutDetaching($selectedCategoryIds);
        });
    }
}
