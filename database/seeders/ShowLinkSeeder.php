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
            $this->seedShowLink($sourceShow->id, $targetShow->id, ShowLinkType::Sequel);

            if ($index % 4 === 0) {
                $this->seedShowLink($sourceShow->id, $targetShow->id, ShowLinkType::SpinOff);
            }
        }
    }

    private function seedShowLink(int $sourceShowId, int $targetShowId, ShowLinkType $type): void
    {
        ShowLink::query()->firstOrCreate([
            'source_show_id' => $sourceShowId,
            'target_show_id' => $targetShowId,
            'type' => $type->value,
        ]);

        $reciprocalType = $type->reciprocalType();

        if ($reciprocalType === null) {
            return;
        }

        ShowLink::query()->firstOrCreate([
            'source_show_id' => $targetShowId,
            'target_show_id' => $sourceShowId,
            'type' => $reciprocalType->value,
        ]);
    }
}
