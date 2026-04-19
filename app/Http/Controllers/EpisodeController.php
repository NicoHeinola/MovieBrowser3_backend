<?php

namespace App\Http\Controllers;

use App\Actions\Episode\CreateEpisodeAction;
use App\Actions\Episode\DeleteEpisodeAction;
use App\Actions\Episode\UpdateEpisodeAction;
use App\Dtos\Episode\CreateEpisodeData;
use App\Dtos\Episode\UpdateEpisodeData;
use App\Http\Requests\Episode\StoreEpisodeRequest;
use App\Http\Requests\Episode\UpdateEpisodeRequest;
use App\Http\Resources\Episode\EpisodeResource;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class EpisodeController extends Controller
{
    public function index(ShowEntry $showEntry): JsonResponse
    {
        $showEntry->loadMissing('show.titles');

        $episodes = QueryBuilder::for($showEntry->episodes())
            ->allowedFilters(...Episode::getAllowedFilters())
            ->allowedSorts(...Episode::getAllowedSorts())
            ->jsonPaginate();

        return EpisodeResource::collection($episodes)->response();
    }

    public function store(ShowEntry $showEntry, StoreEpisodeRequest $request, CreateEpisodeAction $action): JsonResponse
    {
        $episode = $action->handle($showEntry, CreateEpisodeData::from($request->validated()));

        return EpisodeResource::make($episode->load('entry.show.titles'))->response()->setStatusCode(201);
    }

    public function show(ShowEntry $showEntry, Episode $episode): JsonResponse
    {
        return EpisodeResource::make($episode->load('entry.show.titles'))->response();
    }

    public function update(ShowEntry $showEntry, Episode $episode, UpdateEpisodeRequest $request, UpdateEpisodeAction $action): JsonResponse
    {
        $updatedEpisode = $action->handle(UpdateEpisodeData::from([
            ...$request->validated(),
            'episode' => $episode,
        ]));

        return EpisodeResource::make($updatedEpisode->load('entry.show.titles'))->response();
    }

    public function destroy(ShowEntry $showEntry, Episode $episode, DeleteEpisodeAction $action): Response
    {
        $action->handle($episode);

        return response()->noContent();
    }
}
