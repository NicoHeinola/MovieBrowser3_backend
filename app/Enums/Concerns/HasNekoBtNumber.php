<?php

namespace App\Enums\Concerns;

/**
 * For enums nekoBT knows by its own numbers.
 */
trait HasNekoBtNumber
{
    abstract public function getNekoBtNumber(): int;

    /**
     * @return self|null Null for a number the app doesn't know
     */
    public static function fromNekoBtNumber(?int $number): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getNekoBtNumber() === $number) {
                return $case;
            }
        }

        return null;
    }
}
