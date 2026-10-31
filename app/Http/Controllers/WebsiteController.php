<?php

namespace App\Http\Controllers;

use App\Actions\Website\CreateWebsiteAction;
use App\Actions\Website\DeleteWebsiteAction;
use App\Actions\Website\UpdateWebsiteAction;
use App\Dtos\Website\CreateWebsiteData;
use App\Dtos\Website\UpdateWebsiteData;
use App\Http\Requests\Website\StoreWebsiteRequest;
use App\Http\Requests\Website\UpdateWebsiteRequest;
use App\Http\Resources\Website\WebsiteResource;
use App\Models\Website\Website;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class WebsiteController extends Controller
{
    public function index(): JsonResponse
    {
        $websites = QueryBuilder::for(Website::query()->with('tags'))
            ->allowedFilters(...Website::getAllowedFilters())
            ->allowedSorts(...Website::getAllowedSorts())
            ->jsonPaginate();

        return WebsiteResource::collection($websites)->response();
    }

    public function show(Website $website): JsonResponse
    {
        return WebsiteResource::make($website->load('tags'))->response();
    }

    public function store(StoreWebsiteRequest $request, CreateWebsiteAction $action): JsonResponse
    {
        $website = $action->handle(CreateWebsiteData::from($request->validated()));

        return WebsiteResource::make($website)->response()->setStatusCode(201);
    }

    public function update(Website $website, UpdateWebsiteRequest $request, UpdateWebsiteAction $action): JsonResponse
    {
        $updatedWebsite = $action->handle(UpdateWebsiteData::from([
            ...$request->validated(),
            'website' => $website,
        ]));

        return WebsiteResource::make($updatedWebsite)->response();
    }

    public function destroy(Website $website, DeleteWebsiteAction $action): Response
    {
        $action->handle($website);

        return response()->noContent();
    }
}
