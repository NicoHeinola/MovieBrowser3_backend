<?php

namespace App\Rules;

use App\Actions\Episode\VideoFile\CheckVideoBasePathValueAction;
use Closure;

/**
 * The same checks as the setup wizard's, so video files can only go where the containers and VLC see them.
 */
class VideoBasePath extends CodedRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $check = CheckVideoBasePathValueAction::run(is_string($value) ? $value : null);

        if (!$check->ok) {
            $this->failWith($fail, $check->code, $check->params);
        }
    }
}
