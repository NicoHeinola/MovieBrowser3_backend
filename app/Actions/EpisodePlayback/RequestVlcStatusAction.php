<?php

namespace App\Actions\EpisodePlayback;

use App\Dtos\EpisodePlayback\VlcConnectionData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class RequestVlcStatusAction
{
    use AsAction;

    /**
     * Calls VLC's web interface and returns its status (current file, state, position).
     * Passing a command such as ['command' => 'in_enqueue', 'input' => $path] runs it before the status is returned.
     * Returns null when VLC does not answer, which means it has not started yet or was closed.
     *
     * @param  array<string, string>  $command
     * @return array<string, mixed>|null
     */
    public function handle(VlcConnectionData $vlc, array $command = []): ?array
    {
        try {
            $response = Http::withBasicAuth('', $vlc->password)
                ->timeout(2)
                ->get("http://127.0.0.1:{$vlc->port}/requests/status.json", $command);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->json() : null;
    }
}
