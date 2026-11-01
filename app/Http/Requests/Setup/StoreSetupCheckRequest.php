<?php

namespace App\Http\Requests\Setup;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSetupCheckRequest extends FormRequest
{
    private const array CHECKABLE = ['video_base_path', 'vlc_media_player_path'];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // Empty is allowed (it arrives as null): an empty VLC path means `vlc` on the PATH, as for playback
        return [
            'video_base_path' => ['sometimes', 'nullable', 'string'],
            'vlc_media_player_path' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * At least one value to check must be sent, even if empty.
     *
     * @return list<Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (!$this->hasAny(self::CHECKABLE)) {
                    foreach (self::CHECKABLE as $key) {
                        $validator->errors()->add($key, 'Send video_base_path, vlc_media_player_path or both.');
                    }
                }
            },
        ];
    }
}
