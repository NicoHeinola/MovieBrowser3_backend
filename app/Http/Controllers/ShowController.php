<?php

namespace App\Http\Controllers;

use App\Actions\Show\CreateShowAction;
use App\Actions\Show\DeleteShowAction;
use App\Actions\Show\DeleteShowsAction;
use App\Actions\Show\UpdateShowAction;
use App\Http\Requests\Show\DeleteShowsRequest;
use App\Http\Requests\Show\StoreShowRequest;
use App\Http\Requests\Show\UpdateShowRequest;
use App\Models\Show\Show;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;

class ShowController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'shows' => QueryBuilder::for(Show::query()->with('titles'))
                ->allowedFilters(...Show::getAllowedFilters())
                ->allowedSorts(...Show::getAllowedSorts())
                ->defaultSort('-created_at')
                ->get(),
        ]);
    }

    public function store(StoreShowRequest $request, CreateShowAction $createShowAction): JsonResponse
    {
        $validated = $request->validated();
        $titles = $validated['titles'];
        unset($validated['titles']);

        return response()->json([
            'message' => 'Show created successfully.',
            'show' => $createShowAction->handle($validated, $titles),
        ], 201);
    }

    public function show(Show $show): JsonResponse
    {
        return response()->json([
            'show' => $show->load('titles'),
        ]);
    }

    public function update(Show $show, UpdateShowRequest $request, UpdateShowAction $updateShowAction): JsonResponse
    {
        $validated = $request->validated();
        $titles = $validated['titles'] ?? null;
        unset($validated['titles']);

        return response()->json([
            'message' => 'Show updated successfully.',
            'show' => $updateShowAction->handle($show, $validated, $titles),
        ]);
    }

    public function destroy(Show $show, DeleteShowAction $deleteShowAction): JsonResponse
    {
        $deleteShowAction->handle($show);

        return response()->json([
            'message' => 'Show deleted successfully.',
        ]);
    }

    public function destroyMany(DeleteShowsRequest $request, DeleteShowsAction $deleteShowsAction): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'message' => 'Shows deleted successfully.',
            'deleted_count' => $deleteShowsAction->handle($validated['ids']),
        ]);
    }
}
