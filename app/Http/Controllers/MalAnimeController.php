<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mal\IndexMalAnimeRequest;
use App\Http\Resources\Mal\MalAnimeResource;
use App\Services\Mal\MalService;
use Illuminate\Http\JsonResponse;

class MalAnimeController extends Controller
{
    public function index(IndexMalAnimeRequest $request, MalService $malService): JsonResponse
    {
        // Not paginated: MAL's search box answers with about ten matches and no further pages. Resources aren't wrapped
        // app-wide, so the list goes under `data` like every other list, as `BackupController` does
        $anime = $malService->searchAnime($request->validated('filter.search'));

        return response()->json(['data' => MalAnimeResource::collection($anime)]);
    }
}
