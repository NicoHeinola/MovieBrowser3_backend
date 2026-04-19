<?php

namespace App\Http\Controllers;

use App\Actions\Show\CreateShowAction;
use App\Actions\Show\DeleteShowAction;
use App\Actions\Show\DeleteShowsAction;
use App\Actions\Show\UpdateShowAction;
use App\Dtos\Show\CreateShowData;
use App\Dtos\Show\UpdateShowData;
use App\Http\Requests\Show\DeleteShowsRequest;
use App\Http\Requests\Show\StoreShowRequest;
use App\Http\Requests\Show\UpdateShowRequest;
use App\Http\Resources\Show\DeleteShowsResponseResource;
use App\Http\Resources\Show\ShowResource;
use App\Models\Show\Show;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ShowController extends Controller
{
    public function index(): JsonResponse
    {
        $shows = QueryBuilder::for(Show::query()->with('titles', 'entries.episodes', 'incomingLinks.sourceShow'))
            ->allowedFilters(...Show::getAllowedFilters())
            ->allowedSorts(...Show::getAllowedSorts())
            ->jsonPaginate();

        return ShowResource::collection($shows)->response();
    }

    public function store(StoreShowRequest $request, CreateShowAction $createShowAction): JsonResponse
    {
        $show = $createShowAction->handle(CreateShowData::from($request->validated()));

        return ShowResource::make($show->load('titles', 'entries.episodes', 'incomingLinks.sourceShow'))->response()->setStatusCode(201);
    }

    public function show(Show $show): JsonResponse
    {
        return ShowResource::make($show->load('titles', 'entries.episodes', 'incomingLinks.sourceShow'))->response();
    }

    public function update(Show $show, UpdateShowRequest $request, UpdateShowAction $updateShowAction): JsonResponse
    {
        $updatedShow = $updateShowAction->handle(UpdateShowData::from([
            ...$request->validated(),
            'show' => $show,
        ]));

        return ShowResource::make($updatedShow->load('titles', 'entries.episodes', 'incomingLinks.sourceShow'))->response();
    }

    public function destroy(Show $show, DeleteShowAction $deleteShowAction): Response
    {
        $deleteShowAction->handle($show);

        return response()->noContent();
    }

    public function destroyMany(DeleteShowsRequest $request, DeleteShowsAction $deleteShowsAction): JsonResponse
    {
        $validated = $request->validated();
        $deletedCount = $deleteShowsAction->handle($validated['ids']);

        return DeleteShowsResponseResource::make([
            'deleted_count' => $deletedCount,
        ])->response();
    }
}
