<?php

namespace App\Http\Requests\ShowLink;

use App\Enums\ShowLinkType;
use App\Models\Show\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreShowLinkRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Show $show */
        $show = $this->route('show');

        return [
            'target_show_id' => ['required', 'integer', Rule::exists('shows', 'id')],
            'type' => ['required', 'string', Rule::enum(ShowLinkType::class), $this->uniqueLinkRule($show->id)],
        ];
    }

    private function uniqueLinkRule(int $sourceShowId): Unique
    {
        return Rule::unique('show_links', 'type')->where(function ($query) use ($sourceShowId) {
            $query
                ->where('source_show_id', $sourceShowId)
                ->where('target_show_id', $this->input('target_show_id'));
        });
    }
}
