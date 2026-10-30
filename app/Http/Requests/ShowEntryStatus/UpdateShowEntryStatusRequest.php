<?php

namespace App\Http\Requests\ShowEntryStatus;

use App\Enums\ShowWatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateShowEntryStatusRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ShowWatchStatus::class)],
        ];
    }
}
