<?php

namespace Database\Seeders;

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Show::factory(20)
            ->has(ShowTitle::factory()->primary(), 'titles')
            ->has(ShowTitle::factory(2), 'titles')
            ->create();
    }
}
