<?php

namespace App\Actions\Download\Client;

use Lorisleiva\Actions\Concerns\AsAction;

class ResolveMagnetInfoHashAction
{
    use AsAction;

    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * The torrent's info hash in a magnet link, as the 40 lowercase hex digits qBittorrent uses, or null when the link
     * has none. Magnets carry it as hex, or in older links as 32 base32 characters.
     */
    public function handle(string $magnetUrl): ?string
    {
        if (!preg_match('/xt=urn:btih:([a-z0-9]+)/i', $magnetUrl, $matches)) {
            return null;
        }

        $hash = $matches[1];

        if (strlen($hash) === 40 && ctype_xdigit($hash)) {
            return strtolower($hash);
        }

        if (strlen($hash) === 32) {
            return $this->base32ToHex(strtoupper($hash));
        }

        return null;
    }

    /**
     * Decodes base32 five bits at a time into a bit string, then reads it back four bits per hex digit.
     */
    private function base32ToHex(string $base32): ?string
    {
        $bits = '';

        foreach (str_split($base32) as $character) {
            $value = strpos(self::BASE32_ALPHABET, $character);

            if ($value === false) {
                return null;
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $hexDigits = array_map(fn (string $fourBits): string => dechex(bindec($fourBits)), str_split($bits, 4));

        return implode('', $hexDigits);
    }
}
