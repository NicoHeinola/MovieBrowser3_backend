<?php

namespace App\Http\Controllers;

use App\Actions\ShowTitle\CreateShowTitleAction;
use App\Actions\ShowTitle\DeleteShowTitleAction;
use App\Actions\ShowTitle\UpdateShowTitleAction;
use App\Dtos\ShowTitle\CreateShowTitleData;
use App\Dtos\ShowTitle\UpdateShowTitleData;
use App\Http\Requests\ShowTitle\StoreShowTitleRequest;
use App\Http\Requests\ShowTitle\UpdateShowTitleRequest;
use App\Http\Resources\ShowTitle\ShowTitleResource;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ShowTitleController extends Controller
{
    public function index(Show $show): JsonResponse
    {
        $titles = QueryBuilder::for($show->titles()->getQuery())
            ->allowedFilters(...ShowTitle::getAllowedFilters())
            ->allowedSorts(...ShowTitle::getAllowedSorts())
            ->jsonPaginate();

        return ShowTitleResource::collection($titles)->response();
    }

    public function store(Show $show, StoreShowTitleRequest $request, CreateShowTitleAction $action): JsonResponse
    {
        $title = $action->handle($show, CreateShowTitleData::from($request->validated()));

        return ShowTitleResource::make($title)->response()->setStatusCode(201);
    }

    public function show(Show $show, ShowTitle $title): JsonResponse
    {
        return ShowTitleResource::make($title)->response();
    }

    public function update(Show $show, ShowTitle $title, UpdateShowTitleRequest $request, UpdateShowTitleAction $action): JsonResponse
    {
        $updatedTitle = $action->handle(UpdateShowTitleData::from([
            ...$request->validated(),
            'show_title' => $title,
        ]));

        return ShowTitleResource::make($updatedTitle)->response();
    }

    public function destroy(Show $show, ShowTitle $title, DeleteShowTitleAction $action): Response
    {
        $action->handle($title);

        return response()->noContent();
    }
}
