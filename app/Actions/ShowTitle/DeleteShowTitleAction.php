<?php

namespace App\Actions\ShowTitle;

use App\Models\ShowTitle\ShowTitle;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowTitleAction
{
    use AsAction;

    public function handle(ShowTitle $showTitle): void
    {
        if ($showTitle->is_primary) {
            $otherPrimaryExists = $showTitle->show->titles()
                ->where('is_primary', true)
                ->where('id', '!=', $showTitle->id)
                ->exists();

            if (!$otherPrimaryExists) {
                throw ValidationException::withMessages([
                    'title' => ['Cannot delete the only primary title.'],
                ]);
            }
        }

        $showTitle->delete();
    }
}
