<?php

namespace App\Rules;

use App\Actions\Setup\CheckMediaFolderPathAction;
use Closure;

/**
 * The same checks as the setup wizard's, so files can only go where the containers, VLC and the download client see
 * them.
 */
class MediaFolderPath extends CodedRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $check = CheckMediaFolderPathAction::run(is_string($value) ? $value : null);

        if (!$check->ok) {
            $this->failWith($fail, $check->code, $check->params);
        }
    }
}
