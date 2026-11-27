<?php

namespace App\Http\Controllers;

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Http\Requests\NekoBt\IndexNekoBtTorrentRequest;
use App\Http\Resources\NekoBt\NekoBtTorrentResource;
use App\Services\NekoBt\NekoBtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\Paginator;

class NekoBtTorrentController extends Controller
{
    public function index(IndexNekoBtTorrentRequest $request, NekoBtService $nekoBtService): JsonResponse
    {
        $search = NekoBtSearchData::fromQuery($request->validated());
        $page = $nekoBtService->getTorrents($search);

        // Simple pagination: nekoBT says only whether another page follows (`links.next`), not how many there are. The
        // links keep the search and sort; the page goes in as `page[number]`, so the rest of `page` stays out.
        $paginator = (new Paginator($page->torrents, $page->perPage, $page->page, [
            'path' => $request->url(),
            'pageName' => 'page[number]',
        ]))->hasMorePagesWhen($page->hasMore)->appends($request->except('page'));

        return NekoBtTorrentResource::collection($paginator)->response();
    }
}
