<?php

namespace App\Rules;

use App\Actions\EpisodePlayback\CheckVlcBinaryAction;
use App\Enums\SetupCheckCode;
use Closure;

/**
 * The VLC setting decides what playback starts, so it may only ever name VLC.
 */
class VlcBinary extends CodedRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && !CheckVlcBinaryAction::isVlcName($value)) {
            $this->failWith($fail, SetupCheckCode::VlcWrongBinary, ['path' => $value]);
        }
    }
}
