<?php

namespace App\Http\Controllers;

use App\Actions\ShowEntry\CreateShowEntryAction;
use App\Actions\ShowEntry\DeleteShowEntryAction;
use App\Actions\ShowEntry\UpdateShowEntryAction;
use App\Dtos\ShowEntry\CreateShowEntryData;
use App\Dtos\ShowEntry\UpdateShowEntryData;
use App\Http\Requests\ShowEntry\StoreShowEntryRequest;
use App\Http\Requests\ShowEntry\UpdateShowEntryRequest;
use App\Http\Resources\ShowEntry\ShowEntryResource;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ShowEntryController extends Controller
{
    public function index(Show $show): JsonResponse
    {
        $show->loadMissing('titles');

        $entries = QueryBuilder::for($show->entries()->with('episodes'))
            ->allowedFilters(...ShowEntry::getAllowedFilters())
            ->allowedSorts(...ShowEntry::getAllowedSorts())
            ->jsonPaginate();

        return ShowEntryResource::collection($entries)->response();
    }

    public function store(Show $show, StoreShowEntryRequest $request, CreateShowEntryAction $action): JsonResponse
    {
        $entry = $action->handle($show, CreateShowEntryData::from($request->validated()));

        return ShowEntryResource::make($entry->load('show.titles', 'episodes'))->response()->setStatusCode(201);
    }

    public function show(Show $show, ShowEntry $entry): JsonResponse
    {
        return ShowEntryResource::make($entry->load('show.titles', 'episodes'))->response();
    }

    public function update(Show $show, ShowEntry $entry, UpdateShowEntryRequest $request, UpdateShowEntryAction $action): JsonResponse
    {
        $updatedEntry = $action->handle(UpdateShowEntryData::from([
            ...$request->validated(),
            'show_entry' => $entry,
        ]));

        return ShowEntryResource::make($updatedEntry->load('show.titles', 'episodes'))->response();
    }

    public function destroy(Show $show, ShowEntry $entry, DeleteShowEntryAction $action): Response
    {
        $action->handle($entry);

        return response()->noContent();
    }
}
