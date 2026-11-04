<?php

namespace App\Dtos\Setup;

use App\Enums\SetupCheckCode;
use Spatie\LaravelData\Data;

/**
 * The result of checking one setup value: ok, or a code with the values its translated text needs.
 */
class SetupCheckData extends Data
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public bool $ok,
        public ?SetupCheckCode $code = null,
        public array $params = [],
    ) {}

    public static function passed(): self
    {
        return new self(true);
    }

    /**
     * @param  array<string, string>  $params
     */
    public static function failed(SetupCheckCode $code, array $params = []): self
    {
        return new self(false, $code, $params);
    }
}
