<?php

namespace App\Actions\Website;

use App\Models\Website\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncWebsiteTagsAction
{
    use AsAction;

    /**
     * @param  array<int, string>  $names
     */
    public function handle(Website $website, array $names): void
    {
        DB::transaction(function () use ($website, $names): void {
            $names = collect($names)->map(fn (string $name) => trim($name))->filter()->unique()->values();

            $website->tags()->whereNotIn('name', $names)->delete();

            foreach ($names as $name) {
                $website->tags()->firstOrCreate(['name' => $name]);
            }
        });
    }
}
