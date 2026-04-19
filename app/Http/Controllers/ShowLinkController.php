<?php

namespace App\Http\Controllers;

use App\Actions\ShowLink\CreateShowLinkAction;
use App\Actions\ShowLink\DeleteShowLinkAction;
use App\Actions\ShowLink\UpdateShowLinkAction;
use App\Dtos\ShowLink\CreateShowLinkData;
use App\Dtos\ShowLink\UpdateShowLinkData;
use App\Http\Requests\ShowLink\StoreShowLinkRequest;
use App\Http\Requests\ShowLink\UpdateShowLinkRequest;
use App\Http\Resources\ShowLink\ShowLinkResource;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ShowLinkController extends Controller
{
    public function index(Show $show): JsonResponse
    {
        $links = QueryBuilder::for($show->outgoingLinks()->with('targetShow'))
            ->allowedFilters(...ShowLink::getAllowedFilters())
            ->allowedSorts(...ShowLink::getAllowedSorts())
            ->jsonPaginate();

        return ShowLinkResource::collection($links)->response();
    }

    public function store(Show $show, StoreShowLinkRequest $request, CreateShowLinkAction $action): JsonResponse
    {
        $link = $action->handle($show, CreateShowLinkData::from($request->validated()));

        return ShowLinkResource::make($link->load('targetShow'))->response()->setStatusCode(201);
    }

    public function show(Show $show, ShowLink $link): JsonResponse
    {
        return ShowLinkResource::make($link->load('targetShow'))->response();
    }

    public function update(Show $show, ShowLink $link, UpdateShowLinkRequest $request, UpdateShowLinkAction $action): JsonResponse
    {
        $updatedLink = $action->handle(UpdateShowLinkData::from([
            ...$request->validated(),
            'show_link' => $link,
        ]));

        return ShowLinkResource::make($updatedLink->load('targetShow'))->response();
    }

    public function destroy(Show $show, ShowLink $link, DeleteShowLinkAction $action): Response
    {
        $action->handle($link);

        return response()->noContent();
    }
}
