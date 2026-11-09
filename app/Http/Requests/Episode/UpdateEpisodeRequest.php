<?php

namespace App\Http\Requests\Episode;

use App\Models\Episode\Episode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEpisodeRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Episode $episode */
        $episode = $this->route('episode');

        return [
            // An episode can only move between entries of its own show
            'show_entry_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('show_entries', 'id')->where('show_id', $episode->entry->show_id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'file' => ['sometimes', 'required', 'file'],
            'sequence_number' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
