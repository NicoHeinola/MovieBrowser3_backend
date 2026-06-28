<?php

namespace App\Http\Controllers;

use App\Actions\ShowCategory\AttachCategoryToShowAction;
use App\Http\Requests\ShowCategory\StoreShowCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category\Category;
use App\Models\Show\Show;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ShowCategoryController extends Controller
{
    public function store(Show $show, StoreShowCategoryRequest $request, AttachCategoryToShowAction $action): JsonResponse
    {
        /** @var Category $category */
        $category = Category::query()->findOrFail($request->validated()['category_id']);

        $attachedCategory = $action->handle($show, $category);

        return CategoryResource::make($attachedCategory)->response()->setStatusCode(201);
    }

    public function destroy(Show $show, Category $category): Response
    {
        $show->categories()->detach($category->id);

        return response()->noContent();
    }
}
