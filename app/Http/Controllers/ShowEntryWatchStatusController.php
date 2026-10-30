<?php

namespace App\Http\Controllers;

use App\Actions\ShowEntryWatchStatus\DeleteShowEntryWatchStatusAction;
use App\Actions\ShowEntryWatchStatus\UpdateShowEntryWatchStatusAction;
use App\Dtos\ShowEntryWatchStatus\UpdateShowEntryWatchStatusData;
use App\Http\Requests\ShowEntryWatchStatus\UpdateShowEntryWatchStatusRequest;
use App\Http\Resources\ShowEntryWatchStatus\ShowEntryWatchStatusResource;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowEntryWatchStatusController extends Controller
{
    public function update(ShowEntry $showEntry, UpdateShowEntryWatchStatusRequest $request, UpdateShowEntryWatchStatusAction $action): JsonResponse
    {
        $status = $action->handle(UpdateShowEntryWatchStatusData::from([
            ...$request->validated(),
            'show_entry' => $showEntry,
            'user' => $request->user(),
        ]));

        return ShowEntryWatchStatusResource::make($status)->response()->setStatusCode(200);
    }

    public function destroy(ShowEntry $showEntry, Request $request, DeleteShowEntryWatchStatusAction $action): Response
    {
        $action->handle($showEntry, $request->user());

        return response()->noContent();
    }
}
