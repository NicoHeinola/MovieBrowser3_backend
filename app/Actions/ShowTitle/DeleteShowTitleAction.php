<?php

namespace App\Actions\ShowTitle;

use App\Enums\ValidationErrorCode;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowTitleAction
{
    use AsAction;

    public function handle(ShowTitle $showTitle): void
    {
        DB::transaction(function () use ($showTitle): void {
            if ($showTitle->is_primary) {
                $otherPrimaryExists = $showTitle->show->titles()
                    ->where('is_primary', true)
                    ->where('id', '!=', $showTitle->id)
                    ->exists();

                if (!$otherPrimaryExists) {
                    throw ValidationException::withMessages([
                        'title' => [ValidationErrorCode::OnlyPrimaryTitle->value],
                    ]);
                }
            }

            $showTitle->delete();
        });
    }
}
