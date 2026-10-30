<?php

namespace App\Http\Requests\ShowEntryWatchStatus;

use App\Enums\WatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateShowEntryWatchStatusRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(WatchStatus::class)],
        ];
    }
}
