<?php

namespace Database\Seeders;

use App\Enums\ShowEntryType;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Seeder;

class ShowEntrySeeder extends Seeder
{
    /** Probability (0–100) that a show's primary entry is a Season rather than a Movie. */
    private int $seasonPrimaryChance = 80;

    /** Probability (0–100) that a Season show gets a second season. */
    private int $secondSeasonChance = 80;

    /** Probability (0–100) that a two-season show gets a third season. */
    private int $thirdSeasonChance = 70;

    /** Probability (0–100) that a non-Movie show also gets a Movie entry. */
    private int $extraMovieChance = 40;

    /** Probability (0–100) that any show gets a TV Special entry. */
    private int $tvSpecialChance = 70;

    /** Number of extra entries to potentially add (beyond the standard season logic). */
    private int $extraEntryLoopCount = 3;

    public function run(): void
    {
        Show::all()->each(function (Show $show) {
            $types = collect();
            $seasonCounter = 0;

            if (rand(0, 100) < $this->seasonPrimaryChance) {
                $types->push(ShowEntryType::Season);
            } else {
                $types->push(ShowEntryType::Movie);
            }

            // Standard season expansion
            if ($types->contains(ShowEntryType::Season) && rand(0, 100) < $this->secondSeasonChance) {
                $types->push(ShowEntryType::Season);
                if (rand(0, 100) < $this->thirdSeasonChance) {
                    $types->push(ShowEntryType::Season);
                }
            }

            // Loop to add more potential entries
            for ($i = 0; $i < $this->extraEntryLoopCount; $i++) {
                if (!$types->contains(ShowEntryType::Movie) && rand(0, 100) < $this->extraMovieChance) {
                    $types->push(ShowEntryType::Movie);
                }

                if (rand(0, 100) < $this->tvSpecialChance) {
                    $types->push(ShowEntryType::TvSpecial);
                }
            }

            $types->shuffle()->each(function (ShowEntryType $type, int $sortOrder) use ($show, &$seasonCounter) {
                ShowEntry::factory()->for($show)->create([
                    'type' => $type,
                    'name' => match ($type) {
                        ShowEntryType::Season => 'Season '.++$seasonCounter,
                        ShowEntryType::Movie => fake()->words(rand(2, 3), true),
                        ShowEntryType::TvSpecial => 'Special: '.fake()->words(rand(3, 4), true),
                    },
                    'sort_order' => $sortOrder + 1,
                ]);
            });
        });
    }
}
