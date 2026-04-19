<?php

namespace App\Http\Requests\ShowLink;

use App\Enums\ShowLinkType;
use App\Models\ShowLink\ShowLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateShowLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var ShowLink $link */
        $link = $this->route('link');

        return [
            'target_show_id' => ['sometimes', 'required', 'integer', Rule::exists('shows', 'id')],
            'type' => ['sometimes', 'required', 'string', Rule::enum(ShowLinkType::class), $this->uniqueLinkRule($link)],
        ];
    }

    private function uniqueLinkRule(ShowLink $link): Unique
    {
        return Rule::unique('show_links', 'type')
            ->ignore($link)
            ->where(function ($query) use ($link) {
                $query
                    ->where('source_show_id', $link->source_show_id)
                    ->where('target_show_id', $this->input('target_show_id', $link->target_show_id));
            });
    }
}
