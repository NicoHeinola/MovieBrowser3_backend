<?php

namespace App\Http\Requests\Setup;

use App\Enums\SettingKey;
use App\Enums\ValidationErrorCode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSetupCheckRequest extends FormRequest
{
    private const array CHECKABLE = [
        SettingKey::VideoBaseFolder->value,
        SettingKey::VlcMediaPlayerPath->value,
        SettingKey::QBittorrentUrl->value,
        SettingKey::DownloadPath->value,
    ];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // Empty is allowed (it arrives as null): an empty VLC path means `vlc` on the PATH, as for playback
        return [
            SettingKey::VideoBaseFolder->value => ['sometimes', 'nullable', 'string'],
            SettingKey::VlcMediaPlayerPath->value => ['sometimes', 'nullable', 'string'],
            SettingKey::QBittorrentUrl->value => ['sometimes', 'nullable', 'string', 'url:http,https'],
            SettingKey::QBittorrentUsername->value => ['sometimes', 'nullable', 'string'],
            SettingKey::QBittorrentPassword->value => ['sometimes', 'nullable', 'string'],
            SettingKey::DownloadPath->value => ['sometimes', 'nullable', 'string'],
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
                        $validator->errors()->add($key, ValidationErrorCode::Required->value);
                    }
                }
            },
        ];
    }
}
