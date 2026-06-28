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
            ['name' => 'Action', 'value' => 'action', 'icon' => 'bolt'],
            ['name' => 'Adventure', 'value' => 'adventure', 'icon' => 'map'],
            ['name' => 'Comedy', 'value' => 'comedy', 'icon' => 'laugh'],
            ['name' => 'Crime', 'value' => 'crime', 'icon' => 'shield'],
            ['name' => 'Drama', 'value' => 'drama', 'icon' => 'masks'],
            ['name' => 'Fantasy', 'value' => 'fantasy', 'icon' => 'wand'],
            ['name' => 'Historical', 'value' => 'historical', 'icon' => 'landmark'],
            ['name' => 'Horror', 'value' => 'horror', 'icon' => 'ghost'],
            ['name' => 'Mystery', 'value' => 'mystery', 'icon' => 'search'],
            ['name' => 'Romance', 'value' => 'romance', 'icon' => 'heart'],
            ['name' => 'Science Fiction', 'value' => 'science-fiction', 'icon' => 'planet'],
            ['name' => 'Thriller', 'value' => 'thriller', 'icon' => 'flashlight'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['value' => $category['value']],
                ['name' => $category['name'], 'icon' => $category['icon']],
            );
        }
    }
}
