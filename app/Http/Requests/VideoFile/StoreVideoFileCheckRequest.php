<?php

namespace App\Http\Requests\VideoFile;

use App\Actions\Episode\VideoFile\CheckVideoBasePathAction;
use App\Enums\ValidationErrorCode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVideoFileCheckRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
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
                if (VideoFileCheck::query()->active()->exists()) {
                    $validator->errors()->add('video_file_check', ValidationErrorCode::VideoFileCheckRunning->value);

                    return;
                }

                if (!CheckVideoBasePathAction::run()) {
                    $validator->errors()->add('video_base_path', ValidationErrorCode::VideoBasePathUnavailable->value);
                }
            },
        ];
    }
}
