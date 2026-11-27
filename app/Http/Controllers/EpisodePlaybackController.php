<?php

namespace App\Http\Controllers;

use App\Actions\EpisodePlayback\PlayEpisodeAction;
use App\Http\Requests\EpisodePlayback\StoreEpisodePlaybackRequest;
use App\Http\Resources\ShowWatchProgress\ShowWatchProgressResource;
use App\Models\Episode\Episode;
use Illuminate\Http\JsonResponse;

class EpisodePlaybackController extends Controller
{
    public function store(Episode $episode, StoreEpisodePlaybackRequest $request, PlayEpisodeAction $action): JsonResponse
    {
        $progress = $action->handle($episode, $request->user(), $request->boolean('from_start'));

        return ShowWatchProgressResource::make($progress)->response()->setStatusCode(201);
    }
}
