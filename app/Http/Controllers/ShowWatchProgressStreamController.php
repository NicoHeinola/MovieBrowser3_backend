<?php

namespace App\Http\Controllers;

use App\Actions\ShowWatchProgress\StreamShowWatchProgressAction;
use App\Http\Resources\ShowWatchProgress\ShowWatchProgressResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowWatchProgressStreamController extends Controller
{
    /**
     * Server-sent events: a `watch-progress` event per changed show, and a `: ping` comment when idle.
     */
    public function show(Request $request, StreamShowWatchProgressAction $action): StreamedResponse
    {
        $user = $request->user();
        $changes = $action->handle($user, $user->currentAccessToken()->getKey());

        return response()->stream(function () use ($changes, $request): void {
            $this->send("retry: 3000\n\n");
            $lastSentAt = now();

            foreach ($changes as $progresses) {
                $frames = $progresses->map(fn ($progress): string => "event: watch-progress\n"
                    ."id: {$progress->updated_at->toJSON()}\n"
                    .'data: '.json_encode(ShowWatchProgressResource::make($progress)->resolve($request))."\n\n");

                if ($frames->isEmpty() && $lastSentAt->diffInSeconds(now()) >= (int) config('watch_progress.stream_heartbeat_seconds')) {
                    $frames->push(": ping\n\n");
                }

                if ($frames->isNotEmpty()) {
                    $this->send($frames->implode(''));
                    $lastSentAt = now();
                }

                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function send(string $frame): void
    {
        echo $frame;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
