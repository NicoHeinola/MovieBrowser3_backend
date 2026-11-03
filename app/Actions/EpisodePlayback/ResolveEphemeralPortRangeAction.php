<?php

namespace App\Actions\EpisodePlayback;

use Lorisleiva\Actions\Concerns\AsAction;

class ResolveEphemeralPortRangeAction
{
    use AsAction;

    private const string RANGE_FILE = '/proc/sys/net/ipv4/ip_local_port_range';

    /**
     * The ports Linux picks outgoing connections from, as [first, last], or null where that can't be read. Under WSL
     * mirrored networking Windows can't listen on them, and WSL picks a new range on every boot.
     *
     * @return array{int, int}|null
     */
    public function handle(): ?array
    {
        $contents = @file_get_contents(self::RANGE_FILE);

        if ($contents === false || preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $contents, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
