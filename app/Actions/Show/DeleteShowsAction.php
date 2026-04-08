<?php

namespace App\Actions\Show;

use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowsAction
{
    use AsAction;

    public function __construct(private readonly DeleteShowAction $deleteShowAction) {}

    /**
     * @param  array<int, int>  $ids
     */
    public function handle(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $shows = Show::query()
                ->whereKey($ids)
                ->get();

            foreach ($shows as $show) {
                $this->deleteShowAction->handle($show);
            }

            return $shows->count();
        });
    }
}
