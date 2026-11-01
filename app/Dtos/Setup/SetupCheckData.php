<?php

namespace App\Dtos\Setup;

use Spatie\LaravelData\Data;

/**
 * The result of checking one setup value: ok, or a short reason for the user.
 */
class SetupCheckData extends Data
{
    public function __construct(
        public bool $ok,
        public ?string $message,
    ) {}

    public static function fromProblem(?string $problem): self
    {
        return new self($problem === null, $problem);
    }
}
