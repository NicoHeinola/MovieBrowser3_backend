<?php

namespace Database\Seeders;

use App\Enums\ShowEntryType;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Seeder;

class ShowEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Show::all()->each(function (Show $show) {
            $types = collect();
            $seasonCounter = 0;

            // Give every show at least one "main" entry (Season or Movie)
            if (rand(0, 100) < 80) {
                $types->push(ShowEntryType::Season);
            } else {
                $types->push(ShowEntryType::Movie);
            }

            // Maybe add more seasons
            if ($types->contains(ShowEntryType::Season) && rand(0, 100) < 40) {
                $types->push(ShowEntryType::Season);
                if (rand(0, 100) < 30) {
                    $types->push(ShowEntryType::Season);
                }
            }

            // Maybe add a movie if it doesn't have one
            if (!$types->contains(ShowEntryType::Movie) && rand(0, 100) < 20) {
                $types->push(ShowEntryType::Movie);
            }

            // Maybe add TV specials
            if (rand(0, 100) < 30) {
                $types->push(ShowEntryType::TvSpecial);
            }

            $types->shuffle()->each(function (ShowEntryType $type, int $sortOrder) use ($show, &$seasonCounter) {
                ShowEntry::factory()->for($show)->create([
                    'type' => $type,
                    'name' => match ($type) {
                        ShowEntryType::Season => 'Season '.++$seasonCounter,
                        ShowEntryType::Movie => fake()->sentence(rand(2, 3)),
                        ShowEntryType::TvSpecial => 'Special: '.fake()->sentence(rand(3, 4)),
                    },
                    'sort_order' => $sortOrder + 1,
                ]);
            });
        });
    }
}
