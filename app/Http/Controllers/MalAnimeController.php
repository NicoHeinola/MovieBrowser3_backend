<?php

namespace App\Http\Controllers;

use App\Actions\Mal\SearchMalAnimeAction;
use App\Http\Requests\Mal\IndexMalAnimeRequest;
use App\Http\Resources\Mal\MalAnimeResource;
use Illuminate\Http\JsonResponse;

class MalAnimeController extends Controller
{
    public function index(IndexMalAnimeRequest $request, SearchMalAnimeAction $action): JsonResponse
    {
        // Not paginated: MAL's search box answers with about ten matches and no further pages. Resources aren't wrapped
        // app-wide, so the list goes under `data` like every other list, as `BackupController` does
        return response()->json(['data' => MalAnimeResource::collection($action->handle($request->validated('filter.search')))]);
    }
}
