<?php

namespace App\Http\Controllers;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Dtos\Category\CreateCategoryData;
use App\Dtos\Category\UpdateCategoryData;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category\Category;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = QueryBuilder::for(Category::query())
            ->allowedFilters(...Category::getAllowedFilters())
            ->jsonPaginate();

        return CategoryResource::collection($categories)->response();
    }

    public function show(Category $category): JsonResponse
    {
        return CategoryResource::make($category)->response();
    }

    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        $category = $action->handle(CreateCategoryData::from($request->validated()));

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(Category $category, UpdateCategoryRequest $request, UpdateCategoryAction $action): JsonResponse
    {
        $updatedCategory = $action->handle(UpdateCategoryData::from([
            ...$request->validated(),
            'category' => $category,
        ]));

        return CategoryResource::make($updatedCategory)->response();
    }

    public function destroy(Category $category, DeleteCategoryAction $action): Response
    {
        $action->handle($category);

        return response()->noContent();
    }
}
