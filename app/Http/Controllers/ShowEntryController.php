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
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ShowEntryController extends Controller
{
    /**
     * @return array<int|string, string|Closure>
     */
    private function relations(Request $request): array
    {
        return [
            'show.titles',
            'episodes',
            'watchStatuses' => fn (HasMany $query) => $query->where('user_id', $request->user()->id),
        ];
    }

    public function index(Show $show, Request $request): JsonResponse
    {
        $show->loadMissing('titles');

        $entries = QueryBuilder::for($show->entries()->with(['episodes', 'watchStatuses' => fn (HasMany $query) => $query->where('user_id', $request->user()->id)]))
            ->allowedFilters(...ShowEntry::getAllowedFilters())
            ->allowedSorts(...ShowEntry::getAllowedSorts())
            ->jsonPaginate();

        return ShowEntryResource::collection($entries)->response();
    }

    public function store(Show $show, StoreShowEntryRequest $request, CreateShowEntryAction $action): JsonResponse
    {
        $entry = $action->handle($show, CreateShowEntryData::from($request->validated()));

        return ShowEntryResource::make($entry->load($this->relations($request)))->response()->setStatusCode(201);
    }

    public function show(ShowEntry $entry, Request $request): JsonResponse
    {
        return ShowEntryResource::make($entry->load($this->relations($request)))->response();
    }

    public function update(ShowEntry $entry, UpdateShowEntryRequest $request, UpdateShowEntryAction $action): JsonResponse
    {
        $updatedEntry = $action->handle(UpdateShowEntryData::from([
            ...$request->validated(),
            'show_entry' => $entry,
        ]));

        return ShowEntryResource::make($updatedEntry->load($this->relations($request)))->response();
    }

    public function destroy(ShowEntry $entry, DeleteShowEntryAction $action): Response
    {
        $action->handle($entry);

        return response()->noContent();
    }
}
