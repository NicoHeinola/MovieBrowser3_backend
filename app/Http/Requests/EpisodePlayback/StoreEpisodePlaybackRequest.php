<?php

namespace App\Http\Requests\EpisodePlayback;

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Validator;

class StoreEpisodePlaybackRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return list<Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Episode $episode */
                $episode = $this->route('episode');

                if (!File::isFile(ResolveEpisodeVideoPathAction::run($episode))) {
                    $validator->errors()->add('episode', 'The episode has no video file.');
                }
            },
        ];
    }
}
