<?php

namespace App\Rules;

use App\Enums\ValidationErrorCode;
use Closure;

/**
 * Changing the video base folder moves every video file, which only the folder move endpoint does.
 */
class VideoBaseFolderNeedsMove extends CodedRule
{
    /** Runs for an empty value too: clearing the setting would move the files as well */
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->failWith($fail, ValidationErrorCode::VideoBaseFolderNeedsMove);
    }
}
