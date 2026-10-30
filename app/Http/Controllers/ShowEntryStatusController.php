<?php

namespace App\Http\Controllers;

use App\Actions\ShowEntryStatus\DeleteShowEntryStatusAction;
use App\Actions\ShowEntryStatus\UpdateShowEntryStatusAction;
use App\Dtos\ShowEntryStatus\UpdateShowEntryStatusData;
use App\Http\Requests\ShowEntryStatus\UpdateShowEntryStatusRequest;
use App\Http\Resources\ShowEntryStatus\ShowEntryStatusResource;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowEntryStatusController extends Controller
{
    public function update(ShowEntry $showEntry, UpdateShowEntryStatusRequest $request, UpdateShowEntryStatusAction $action): JsonResponse
    {
        $status = $action->handle(UpdateShowEntryStatusData::from([
            ...$request->validated(),
            'show_entry' => $showEntry,
            'user' => $request->user(),
        ]));

        return ShowEntryStatusResource::make($status)->response()->setStatusCode(200);
    }

    public function destroy(ShowEntry $showEntry, Request $request, DeleteShowEntryStatusAction $action): Response
    {
        $action->handle($showEntry, $request->user());

        return response()->noContent();
    }
}
