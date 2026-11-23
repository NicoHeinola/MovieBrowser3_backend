<?php

namespace Database\Seeders;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * The shows the seeders create, one JSON file per show in `data/shows`. A show's first title is its primary one, and
 * links name other catalog shows by that title.
 */
final class ShowCatalog
{
    /**
     * Keyed by the show's primary title.
     *
     * @return Collection<string, array{
     *     titles: list<string>,
     *     description: string,
     *     categories: list<string>,
     *     card_image_url: string,
     *     banner_url: string,
     *     preview_url: string|null,
     *     entries: list<array{type: string, name: string, url: string|null, episodes: list<array{name: string, duration_seconds: int}>}>,
     *     links: list<array{type: string, title: string}>,
     * }>
     */
    public function shows(): Collection
    {
        return collect(File::glob(database_path('seeders/data/shows/*.json')))
            ->map(fn (string $path): array => File::json($path, JSON_THROW_ON_ERROR))
            ->keyBy(fn (array $show): string => $show['titles'][0]);
    }
}
