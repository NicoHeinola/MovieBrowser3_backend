<?php

namespace App\Http\Controllers;

use App\Actions\EpisodePlayback\CreateEpisodePlaybackAction;
use App\Http\Requests\EpisodePlayback\StoreEpisodePlaybackRequest;
use App\Http\Resources\ShowWatchProgress\ShowWatchProgressResource;
use App\Models\Episode\Episode;
use Illuminate\Http\JsonResponse;

class EpisodePlaybackController extends Controller
{
    public function store(Episode $episode, StoreEpisodePlaybackRequest $request, CreateEpisodePlaybackAction $action): JsonResponse
    {
        $progress = $action->handle($episode, $request->user());

        return ShowWatchProgressResource::make($progress)->response()->setStatusCode(201);
    }
}
