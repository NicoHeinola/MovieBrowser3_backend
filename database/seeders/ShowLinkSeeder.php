<?php

namespace Database\Seeders;

use App\Enums\ShowLinkType;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use Illuminate\Database\Seeder;

class ShowLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shows = Show::all();

        for ($index = 0; $index < $shows->count() - 1; $index++) {
            $sourceShow = $shows[$index];
            $targetShow = $shows[$index + 1];

            ShowLink::factory()->create([
                'source_show_id' => $sourceShow->id,
                'target_show_id' => $targetShow->id,
                'type' => ShowLinkType::Sequel,
            ]);

            ShowLink::factory()->prequel()->create([
                'source_show_id' => $targetShow->id,
                'target_show_id' => $sourceShow->id,
            ]);
        }
    }
}
