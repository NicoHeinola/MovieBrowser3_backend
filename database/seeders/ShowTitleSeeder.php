<?php

namespace Database\Seeders;

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Seeder;

class ShowTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Show::all()->each(function (Show $show) {
            ShowTitle::factory()->primary()->for($show)->create();
            ShowTitle::factory(rand(1, 5))->for($show)->create();
        });
    }
}
