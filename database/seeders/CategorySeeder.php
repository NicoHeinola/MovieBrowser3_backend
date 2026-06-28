<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Action', 'value' => 'action', 'icon' => 'mdi-lightning-bolt'],
            ['name' => 'Adventure', 'value' => 'adventure', 'icon' => 'mdi-map'],
            ['name' => 'Comedy', 'value' => 'comedy', 'icon' => 'mdi-emoticon-happy'],
            ['name' => 'Crime', 'value' => 'crime', 'icon' => 'mdi-police-badge'],
            ['name' => 'Drama', 'value' => 'drama', 'icon' => 'mdi-drama-masks'],
            ['name' => 'Fantasy', 'value' => 'fantasy', 'icon' => 'mdi-wizard-hat'],
            ['name' => 'Historical', 'value' => 'historical', 'icon' => 'mdi-timeline-clock'],
            ['name' => 'Horror', 'value' => 'horror', 'icon' => 'mdi-ghost'],
            ['name' => 'Mystery', 'value' => 'mystery', 'icon' => 'mdi-magnify'],
            ['name' => 'Romance', 'value' => 'romance', 'icon' => 'mdi-heart'],
            ['name' => 'Science Fiction', 'value' => 'science-fiction', 'icon' => 'mdi-rocket-launch'],
            ['name' => 'Thriller', 'value' => 'thriller', 'icon' => 'mdi-flashlight'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['value' => $category['value']],
                ['name' => $category['name'], 'icon' => $category['icon']],
            );
        }
    }
}
