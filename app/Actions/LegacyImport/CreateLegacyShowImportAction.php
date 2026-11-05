<?php

namespace App\Actions\LegacyImport;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Dtos\Category\CreateCategoryData;
use App\Dtos\LegacyImport\LegacyCategoryData;
use App\Dtos\LegacyImport\LegacyEpisodeData;
use App\Dtos\LegacyImport\LegacySeasonData;
use App\Dtos\LegacyImport\LegacyShowData;
use App\Dtos\LegacyImport\LegacyUserWatchSeasonData;
use App\Enums\ShowEntryType;
use App\Enums\WatchStatus;
use App\Models\Category\Category;
use App\Models\Episode\Episode;
use App\Models\LegacyShowImport\LegacyShowImport;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class CreateLegacyShowImportAction
{
    use AsAction;

    public function __construct(
        private readonly CopyVideoFileAction $copyVideoFileAction,
        private readonly CreateCategoryAction $createCategoryAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly ResolveLegacyEpisodeSourcePathAction $resolveLegacyEpisodeSourcePathAction,
    ) {}

    /**
     * Imports one MovieBrowser2 show and copies its videos; the originals are only read. Each season becomes an entry,
     * with its OVAs and movies kept in place, so the watch order stays. A season of only movies or only OVAs becomes a
     * movie or TV special entry. Only the videos in `$missingFiles` (what the check found missing) are imported without
     * one; any other video that can't be read, e.g. because the drive dropped out, fails the show.
     *
     * Any failure, including `$onFileCopied` throwing to cancel, rolls back the whole show and deletes its copies.
     * `$copyWithWindows` copies through Windows' robocopy (see CopyVideoFileAction).
     *
     * @param  list<string>  $missingFiles
     * @param  (Closure(string $sourcePath, int $bytes): void)|null  $onFileCopied
     */
    public function handle(LegacyShowData $legacyShow, ?string $sourceRoot = null, array $missingFiles = [], ?Closure $onFileCopied = null, bool $copyWithWindows = false): LegacyShowImport
    {
        return DB::transaction(function () use ($legacyShow, $sourceRoot, $missingFiles, $onFileCopied, $copyWithWindows): LegacyShowImport {
            if (LegacyShowImport::query()->where('legacy_show_id', $legacyShow->id)->exists()) {
                throw new RuntimeException("MovieBrowser2 show {$legacyShow->id} has already been imported.");
            }

            $show = Show::query()->create([
                'banner_url' => $legacyShow->image ?? '',
                'card_image_url' => $legacyShow->image ?? '',
                'preview_url' => null,
                'description' => filled($legacyShow->description) ? $legacyShow->description : null,
            ]);

            $show->titles()->create(['title' => Str::squish($legacyShow->title), 'is_primary' => true]);
            $show->categories()->sync($this->resolveCategoryIds($legacyShow->categories));

            /** @var array<int, ShowEntry> $entriesBySeasonId */
            $entriesBySeasonId = [];
            /** @var list<array{Episode, string}> $copies */
            $copies = [];

            $seasons = collect($legacyShow->seasons)->sortBy([['number', 'asc'], ['id', 'asc']])->values();

            foreach ($seasons as $index => $season) {
                $entry = $show->entries()->create([
                    'type' => $this->entryType($season),
                    'name' => $this->entryName($season),
                    'sort_order' => $index + 1,
                ]);

                $entriesBySeasonId[$season->id] = $entry;

                foreach (collect($season->episodes)->sortBy([['number', 'asc'], ['id', 'asc']]) as $legacyEpisode) {
                    $sourcePath = $this->resolveLegacyEpisodeSourcePathAction->handle($legacyEpisode, $sourceRoot);
                    // Not File::isFile: a video that vanished since the check must fail the copy, not drop silently
                    $hasVideo = $sourcePath !== null && !in_array($sourcePath, $missingFiles, true);

                    $episode = $entry->episodes()->create([
                        'name' => $this->episodeName($legacyEpisode),
                        'filename' => $hasVideo ? basename((string) $legacyEpisode->filename) : '',
                        'has_video_file' => $hasVideo,
                        'sequence_number' => $legacyEpisode->number,
                    ]);

                    if ($hasVideo) {
                        $copies[] = [$episode, $sourcePath];
                    }
                }
            }

            $this->createWatchStatuses($legacyShow, $entriesBySeasonId);

            $import = LegacyShowImport::query()->create([
                'legacy_show_id' => $legacyShow->id,
                'show_id' => $show->id,
            ]);

            // Files last, so a failed copy rolls back every row above; each copy deletes itself on rollback
            foreach ($copies as [$episode, $sourcePath]) {
                $bytes = $this->copyVideoFileAction->handle($sourcePath, $this->resolveEpisodeVideoPathAction->handle($episode), $copyWithWindows);

                if ($onFileCopied !== null) {
                    $onFileCopied($sourcePath, $bytes);
                }
            }

            return $import;
        });
    }

    /**
     * Matches MovieBrowser2 category names to existing categories by name or value, ignoring case and punctuation
     * (`slice_of_life` finds "Slice of Life"), and creates the ones that don't exist.
     *
     * @param  array<int, LegacyCategoryData>  $legacyCategories
     * @return list<int>
     */
    private function resolveCategoryIds(array $legacyCategories): array
    {
        $categories = Category::query()->get();

        return collect($legacyCategories)
            ->map(function (LegacyCategoryData $legacyCategory) use ($categories): int {
                $key = $this->categoryKey($legacyCategory->name);

                $category = $categories->first(fn (Category $category): bool => $this->categoryKey($category->value) === $key || $this->categoryKey($category->name) === $key);

                if ($category === null) {
                    $category = $this->createCategoryAction->handle(new CreateCategoryData(
                        name: Str::ucfirst(str_replace('_', ' ', $legacyCategory->name)),
                        value: $legacyCategory->name,
                    ));

                    $categories->push($category);
                }

                return $category->id;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function categoryKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($value)) ?? '';
    }

    private function entryType(LegacySeasonData $season): ShowEntryType
    {
        $types = collect($season->episodes)->map(fn (LegacyEpisodeData $episode): string => $episode->type)->unique()->values()->all();

        return match ($types) {
            ['movie'] => ShowEntryType::Movie,
            ['ova'] => ShowEntryType::TvSpecial,
            default => ShowEntryType::Season,
        };
    }

    /**
     * The season's title, else the only episode's title for a movie or OVA entry, else "Season 2", "Movie" or "OVA".
     */
    private function entryName(LegacySeasonData $season): string
    {
        $title = Str::squish($season->title ?? '');

        if ($title !== '') {
            return $title;
        }

        $type = $this->entryType($season);

        if ($type !== ShowEntryType::Season && count($season->episodes) === 1) {
            $title = Str::squish($season->episodes[0]->title ?? '');
        }

        if ($title !== '') {
            return $title;
        }

        return match ($type) {
            ShowEntryType::Movie => 'Movie',
            ShowEntryType::TvSpecial => 'OVA',
            default => "Season {$season->number}",
        };
    }

    private function episodeName(LegacyEpisodeData $episode): string
    {
        $title = Str::squish($episode->title ?? '');

        if ($title !== '') {
            return $title;
        }

        return match ($episode->type) {
            'ova' => "OVA {$episode->number}",
            'movie' => "Movie {$episode->number}",
            default => "Episode {$episode->number}",
        };
    }

    /**
     * MovieBrowser2 kept one status per user and show, MovieBrowser3 keeps one per entry. A completed show completes
     * every entry. For watching, on hold and dropped, when MovieBrowser2 knows the season the user was on, earlier
     * entries count as completed, that one gets the status and later ones get none; otherwise every entry gets it.
     * Users without a MovieBrowser3 account (matched by username, ignoring case) are skipped.
     *
     * @param  array<int, ShowEntry>  $entriesBySeasonId
     */
    private function createWatchStatuses(LegacyShowData $legacyShow, array $entriesBySeasonId): void
    {
        $entries = array_values($entriesBySeasonId);

        foreach ($legacyShow->userShowStatuses as $legacyStatus) {
            $status = WatchStatus::tryFrom($legacyStatus->status);
            $user = User::query()->usernameIgnoringCase($legacyStatus->username)->first();

            if ($status === null || $user === null) {
                continue;
            }

            $currentIndex = $this->currentEntryIndex($legacyShow->userWatchSeasons, $legacyStatus->username, $entriesBySeasonId);
            $splitsBySeason = $currentIndex !== null && in_array($status, [WatchStatus::Watching, WatchStatus::OnHold, WatchStatus::Dropped], true);

            foreach ($entries as $index => $entry) {
                $entryStatus = match (true) {
                    !$splitsBySeason, $index === $currentIndex => $status,
                    $index < $currentIndex => WatchStatus::Completed,
                    default => null,
                };

                if ($entryStatus !== null) {
                    ShowEntryWatchStatus::query()->create([
                        'show_entry_id' => $entry->id,
                        'user_id' => $user->id,
                        'status' => $entryStatus,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, LegacyUserWatchSeasonData>  $watchSeasons
     * @param  array<int, ShowEntry>  $entriesBySeasonId
     */
    private function currentEntryIndex(array $watchSeasons, string $username, array $entriesBySeasonId): ?int
    {
        $seasonId = collect($watchSeasons)
            ->first(fn (LegacyUserWatchSeasonData $watchSeason): bool => mb_strtolower($watchSeason->username) === mb_strtolower($username))
            ?->seasonId;

        $index = $seasonId === null ? false : array_search($seasonId, array_keys($entriesBySeasonId), true);

        return $index === false ? null : $index;
    }
}
