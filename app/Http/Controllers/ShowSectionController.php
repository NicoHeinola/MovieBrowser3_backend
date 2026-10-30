<?php

namespace App\Http\Controllers;

use App\Actions\ShowSection\CreateShowSectionAction;
use App\Actions\ShowSection\DeleteShowSectionAction;
use App\Actions\ShowSection\UpdateShowSectionAction;
use App\Dtos\ShowSection\CreateShowSectionData;
use App\Dtos\ShowSection\UpdateShowSectionData;
use App\Http\Requests\ShowSection\StoreShowSectionRequest;
use App\Http\Requests\ShowSection\UpdateShowSectionRequest;
use App\Http\Resources\ShowSection\ShowSectionResource;
use App\Models\ShowSection\ShowSection;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ShowSectionController extends Controller
{
    public function index(): JsonResponse
    {
        $sections = QueryBuilder::for(ShowSection::query()->with('category')->orderBy('sort_order')->orderBy('id'))
            ->allowedFilters(...ShowSection::getAllowedFilters())
            ->allowedSorts(...ShowSection::getAllowedSorts())
            ->jsonPaginate();

        return ShowSectionResource::collection($sections)->response();
    }

    public function store(StoreShowSectionRequest $request, CreateShowSectionAction $action): JsonResponse
    {
        $section = $action->handle(CreateShowSectionData::from($request->validated()));

        return ShowSectionResource::make($section->load('category'))->response()->setStatusCode(201);
    }

    public function show(ShowSection $showSection): JsonResponse
    {
        return ShowSectionResource::make($showSection->load('category'))->response();
    }

    public function update(ShowSection $showSection, UpdateShowSectionRequest $request, UpdateShowSectionAction $action): JsonResponse
    {
        $updatedSection = $action->handle(UpdateShowSectionData::from([
            ...$request->validated(),
            'show_section' => $showSection,
        ]));

        return ShowSectionResource::make($updatedSection->load('category'))->response();
    }

    public function destroy(ShowSection $showSection, DeleteShowSectionAction $action): Response
    {
        $action->handle($showSection);

        return response()->noContent();
    }
}
