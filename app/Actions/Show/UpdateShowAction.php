<?php

namespace App\Actions\Show;

use App\Actions\ShowTitle\SyncShowTitlesAction;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowAction
{
    use AsAction;

    public function __construct(private readonly SyncShowTitlesAction $syncShowTitlesAction) {}

    /**
     * @param  array<string, string|null>  $attributes
     * @param  array<int, array{title: string, is_primary: bool}>|null  $titles
     */
    public function handle(Show $show, array $attributes, ?array $titles = null): Show
    {
        return DB::transaction(function () use ($show, $attributes, $titles): Show {
            if ($attributes !== []) {
                $show->update($attributes);
            }

            if ($titles !== null) {
                $this->syncShowTitlesAction->handle($show, $titles);
            }

            return $show->fresh()->load('titles');
        });
    }
}
