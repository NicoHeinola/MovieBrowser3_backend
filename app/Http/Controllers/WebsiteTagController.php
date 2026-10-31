<?php

namespace App\Http\Controllers;

use App\Actions\WebsiteTag\CreateWebsiteTagAction;
use App\Actions\WebsiteTag\DeleteWebsiteTagAction;
use App\Actions\WebsiteTag\UpdateWebsiteTagAction;
use App\Dtos\WebsiteTag\CreateWebsiteTagData;
use App\Dtos\WebsiteTag\UpdateWebsiteTagData;
use App\Http\Requests\WebsiteTag\StoreWebsiteTagRequest;
use App\Http\Requests\WebsiteTag\UpdateWebsiteTagRequest;
use App\Http\Resources\WebsiteTag\WebsiteTagResource;
use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class WebsiteTagController extends Controller
{
    public function index(): JsonResponse
    {
        $websiteTags = QueryBuilder::for(WebsiteTag::query()->withCount('websites'))
            ->allowedFilters(...WebsiteTag::getAllowedFilters())
            ->allowedSorts(...WebsiteTag::getAllowedSorts())
            ->jsonPaginate();

        return WebsiteTagResource::collection($websiteTags)->response();
    }

    public function show(WebsiteTag $websiteTag): JsonResponse
    {
        return WebsiteTagResource::make($websiteTag->loadCount('websites'))->response();
    }

    public function store(StoreWebsiteTagRequest $request, CreateWebsiteTagAction $action): JsonResponse
    {
        $websiteTag = $action->handle(CreateWebsiteTagData::from($request->validated()));

        return WebsiteTagResource::make($websiteTag->loadCount('websites'))->response()->setStatusCode(201);
    }

    public function update(WebsiteTag $websiteTag, UpdateWebsiteTagRequest $request, UpdateWebsiteTagAction $action): JsonResponse
    {
        $updatedWebsiteTag = $action->handle(UpdateWebsiteTagData::from([
            ...$request->validated(),
            'website_tag' => $websiteTag,
        ]));

        return WebsiteTagResource::make($updatedWebsiteTag->loadCount('websites'))->response();
    }

    public function destroy(WebsiteTag $websiteTag, DeleteWebsiteTagAction $action): Response
    {
        $action->handle($websiteTag);

        return response()->noContent();
    }
}
