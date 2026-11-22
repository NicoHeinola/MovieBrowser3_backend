<?php

namespace App\Http\Controllers;

use App\Actions\ShowTemplate\GetShowTemplateAction;
use App\Http\Requests\ShowTemplate\ShowShowTemplateRequest;
use App\Http\Resources\ShowTemplate\ShowTemplateResource;

class ShowTemplateController extends Controller
{
    public function show(ShowShowTemplateRequest $request, GetShowTemplateAction $action): ShowTemplateResource
    {
        return new ShowTemplateResource($action->handle($request->validated('url')));
    }
}
