<?php

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Show\DeleteShowsAction;
use App\Models\Category\Category;
use App\Models\Episode\Episode;
use App\Models\Setting\Setting;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowTitle\ShowTitle;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show endpoints require authentication', function () {
    getJson('/api/v1/shows')->assertUnauthorized();
    postJson('/api/v1/shows', [])->assertUnauthorized();
    deleteJson('/api/v1/shows', ['ids' => [1]])->assertUnauthorized();
});

test('authenticated non-admin users can read shows', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();

    withToken($user->createToken('test-token')->plainTextToken);

    getJson('/api/v1/shows')
        ->assertOk()
        ->assertJsonPath('data.0.id', $show->id);

    getJson("/api/v1/shows/{$show->id}")
        ->assertOk()
        ->assertJsonPath('id', $show->id);
});

test('show write endpoints forbid authenticated non-admin users', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    postJson('/api/v1/shows', [])
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');

    patchJson("/api/v1/shows/{$show->id}", [])
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');

    deleteJson("/api/v1/shows/{$show->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');

    deleteJson('/api/v1/shows', ['ids' => [1]])
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('an admin can create a show', function () {
    actingAsAdmin();

    $response = postJson('/api/v1/shows', [
        'banner_url' => ' https://cdn.example.com/banners/severance.jpg ',
        'card_image_url' => 'https://cdn.example.com/cards/severance.jpg',
        'preview_url' => '',
        'description' => 'A group of office workers whose memories have been surgically divided between their work and personal lives.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('banner_url', 'https://cdn.example.com/banners/severance.jpg')
        ->assertJsonPath('preview_url', null)
        ->assertJsonPath('description', 'A group of office workers whose memories have been surgically divided between their work and personal lives.');

    assertDatabaseHas('shows', [
        'banner_url' => 'https://cdn.example.com/banners/severance.jpg',
        'card_image_url' => 'https://cdn.example.com/cards/severance.jpg',
        'preview_url' => null,
        'description' => 'A group of office workers whose memories have been surgically divided between their work and personal lives.',
    ]);
});

test('an admin can list and fetch shows with titles', function () {
    actingAsAdmin();

    $show = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/the-bear.jpg',
    ]);

    ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'The Bear',
    ]);
    ShowTitle::factory()->for($show)->create([
        'title' => 'The Bear International',
    ]);

    getJson('/api/v1/shows')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.banner_url', 'https://cdn.example.com/banners/the-bear.jpg')
        ->assertJsonPath('data.0.titles.0.title', 'The Bear')
        ->assertJsonCount(2, 'data.0.titles');

    getJson("/api/v1/shows/{$show->id}")
        ->assertOk()
        ->assertJsonPath('id', $show->id)
        ->assertJsonPath('titles.0.title', 'The Bear')
        ->assertJsonCount(2, 'titles')
        ->assertJsonPath('created_at', $show->created_at->toJSON())
        ->assertJsonPath('updated_at', $show->updated_at->toJSON());
});

test('an admin can sort shows by random', function () {
    actingAsAdmin();

    Show::factory()->count(10)->hasTitles(1, ['is_primary' => true])->create();

    getJson('/api/v1/shows?sort=random')
        ->assertOk()
        ->assertJsonCount(10, 'data');
});

test('an admin can filter shows by related title through query builder', function () {
    actingAsAdmin();

    $matchingShow = Show::factory()->create();
    $otherShow = Show::factory()->create();

    ShowTitle::factory()->for($matchingShow)->primary()->create([
        'title' => 'The Bear',
    ]);
    ShowTitle::factory()->for($otherShow)->primary()->create([
        'title' => 'Andor',
    ]);

    getJson('/api/v1/shows?filter[title]=like:bear')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingShow->id)
        ->assertJsonPath('data.0.titles.0.title', 'The Bear');
});

test('an admin can search shows by related title through query builder', function () {
    actingAsAdmin();

    $matchingShow = Show::factory()->create();
    $otherShow = Show::factory()->create();

    ShowTitle::factory()->for($matchingShow)->primary()->create([
        'title' => 'The Bear',
    ]);
    ShowTitle::factory()->for($otherShow)->primary()->create([
        'title' => 'Andor',
    ]);

    getJson('/api/v1/shows?filter[search]=bear')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingShow->id)
        ->assertJsonPath('data.0.titles.0.title', 'The Bear');
});

test('show search ignores case and matches % and _ literally', function () {
    actingAsAdmin();

    $bear = Show::factory()->create();
    $percent = Show::factory()->create();
    $snake = Show::factory()->create();

    ShowTitle::factory()->for($bear)->primary()->create(['title' => 'The Bear']);
    ShowTitle::factory()->for($percent)->primary()->create(['title' => '100% Love']);
    ShowTitle::factory()->for($snake)->primary()->create(['title' => 'snake_case']);

    getJson('/api/v1/shows?filter[search]=tHE bEAR')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bear->id);

    getJson('/api/v1/shows?filter[search]='.urlencode('100%'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $percent->id);

    getJson('/api/v1/shows?filter[search]=e_c')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $snake->id);
});

test('show search matches the description, titles, category names and entry names', function () {
    actingAsAdmin();

    $described = Show::factory()->create(['description' => 'A chef returns to Chicago']);
    $titled = Show::factory()->create(['description' => null]);
    $categorized = Show::factory()->create(['description' => null]);
    $withEntry = Show::factory()->create(['description' => null]);
    Show::factory()->create(['description' => null]);

    ShowTitle::factory()->for($titled)->create(['title' => 'Alternative Kitchen Name', 'is_primary' => false]);
    $categorized->categories()->attach(Category::factory()->create(['name' => 'Thriller']));
    ShowEntry::factory()->for($withEntry)->create(['name' => 'Mugen Train']);

    getJson('/api/v1/shows?filter[search]=CHICAGO')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $described->id);

    getJson('/api/v1/shows?filter[search]=kitchen')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $titled->id);

    getJson('/api/v1/shows?filter[search]=thrill')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $categorized->id);

    getJson('/api/v1/shows?filter[search]=mugen')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $withEntry->id);
});

test('show search needs every word to match, each in any field', function () {
    actingAsAdmin();

    $match = Show::factory()->create(['description' => 'Set in a Chicago restaurant']);
    $titleOnly = Show::factory()->create(['description' => null]);

    ShowTitle::factory()->for($match)->primary()->create(['title' => 'The Bear']);
    ShowTitle::factory()->for($titleOnly)->primary()->create(['title' => 'Bear Grylls']);
    $match->categories()->attach(Category::factory()->create(['name' => 'Drama']));

    getJson('/api/v1/shows?filter[search]='.urlencode('  drama   bear chicago '))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);

    getJson('/api/v1/shows?filter[search]='.urlencode('bear weather'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('show search stays inside the other filters', function () {
    actingAsAdmin();

    $anime = Category::factory()->create(['value' => 'anime']);
    $inCategory = Show::factory()->create(['description' => 'Pirates at sea']);
    $outsideCategory = Show::factory()->create(['description' => 'Pirates in the Caribbean']);

    $inCategory->categories()->attach($anime);

    getJson('/api/v1/shows?filter[search]=pirates&filter[categories.value]=anime')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inCategory->id);

    getJson('/api/v1/shows?filter[search]=pirates&filter[exclude_categories]=anime')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $outsideCategory->id);
});

test('an admin can filter shows with metadata operators', function () {
    actingAsAdmin();

    $showWithUrl = Show::factory()->create([
        'preview_url' => 'https://cdn.example.com/previews/andor.mp4',
    ]);
    $showWithoutUrl = Show::factory()->create([
        'preview_url' => null,
    ]);

    ShowTitle::factory()->for($showWithUrl)->primary()->create(['title' => 'Andor']);
    ShowTitle::factory()->for($showWithoutUrl)->primary()->create(['title' => 'Severance']);

    getJson('/api/v1/shows?filter[preview_url]=null')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $showWithoutUrl->id);

    getJson('/api/v1/shows?filter[preview_url]=not_null')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $showWithUrl->id);

    getJson('/api/v1/shows?filter[preview_url]=exact:https://cdn.example.com/previews/andor.mp4')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $showWithUrl->id);
});

test('an admin can sort shows through query builder', function () {
    actingAsAdmin();

    $laterShow = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/zeta.jpg',
    ]);
    $earlierShow = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/alpha.jpg',
    ]);

    ShowTitle::factory()->for($laterShow)->primary()->create([
        'title' => 'Zeta Show',
    ]);
    ShowTitle::factory()->for($earlierShow)->primary()->create([
        'title' => 'Alpha Show',
    ]);

    getJson('/api/v1/shows?sort=banner_url')
        ->assertOk()
        ->assertJsonPath('data.0.id', $earlierShow->id)
        ->assertJsonPath('data.1.id', $laterShow->id);

    getJson('/api/v1/shows?sort=-banner_url')
        ->assertOk()
        ->assertJsonPath('data.0.id', $laterShow->id)
        ->assertJsonPath('data.1.id', $earlierShow->id);
});

test('an admin can update a show', function () {
    actingAsAdmin();

    $show = Show::factory()->create([
        'preview_url' => 'https://cdn.example.com/previews/original.mp4',
    ]);

    $response = patchJson("/api/v1/shows/{$show->id}", [
        'banner_url' => 'https://cdn.example.com/banners/updated.jpg',
        'preview_url' => '',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('banner_url', 'https://cdn.example.com/banners/updated.jpg')
        ->assertJsonPath('preview_url', null);

    assertDatabaseHas('shows', [
        'id' => $show->id,
        'banner_url' => 'https://cdn.example.com/banners/updated.jpg',
        'preview_url' => null,
    ]);
});

test('an admin can delete a show and its titles cascade', function () {
    global $videoBasePath;

    actingAsAdmin();

    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);

    $title = ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Dr. Stone',
    ]);

    $episodePath = "$videoBasePath/{$show->id}_Dr. Stone/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv";
    File::ensureDirectoryExists(dirname($episodePath));
    File::put($episodePath, 'video-bytes');

    deleteJson("/api/v1/shows/{$show->id}")
        ->assertNoContent();

    assertDatabaseMissing('shows', [
        'id' => $show->id,
    ]);

    assertDatabaseMissing('show_titles', [
        'id' => $title->id,
    ]);

    expect(File::exists($episodePath))->toBeFalse();
});

test('an admin can delete multiple shows at once', function () {
    actingAsAdmin();

    $firstShow = Show::factory()->create();
    $secondShow = Show::factory()->create();
    $remainingShow = Show::factory()->create();

    $firstTitle = ShowTitle::factory()->for($firstShow)->primary()->create();
    $secondTitle = ShowTitle::factory()->for($secondShow)->primary()->create();
    $remainingTitle = ShowTitle::factory()->for($remainingShow)->primary()->create();

    deleteJson('/api/v1/shows', [
        'ids' => [$firstShow->id, $secondShow->id],
    ])
        ->assertOk()
        ->assertJsonPath('deleted_count', 2);

    assertDatabaseMissing('shows', [
        'id' => $firstShow->id,
    ]);

    assertDatabaseMissing('shows', [
        'id' => $secondShow->id,
    ]);

    assertDatabaseHas('shows', [
        'id' => $remainingShow->id,
    ]);

    assertDatabaseMissing('show_titles', [
        'id' => $firstTitle->id,
    ]);

    assertDatabaseMissing('show_titles', [
        'id' => $secondTitle->id,
    ]);

    assertDatabaseHas('show_titles', [
        'id' => $remainingTitle->id,
    ]);
});

test('bulk show deletion validates the ids payload', function () {
    actingAsAdmin();

    deleteJson('/api/v1/shows', [
        'ids' => ['abc', 999999],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0', 'ids.1']);
});

test('an admin can update a show without affecting its titles', function () {
    actingAsAdmin();

    $show = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/original.jpg',
    ]);

    ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Still Here',
    ]);

    patchJson("/api/v1/shows/{$show->id}", [
        'card_image_url' => 'https://cdn.example.com/cards/new-card.jpg',
    ])
        ->assertOk()
        ->assertJsonPath('card_image_url', 'https://cdn.example.com/cards/new-card.jpg')
        ->assertJsonCount(1, 'titles');

    assertDatabaseHas('show_titles', [
        'show_id' => $show->id,
        'title' => 'Still Here',
        'is_primary' => true,
    ]);
});

test('filter values containing commas are matched literally', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    ShowTitle::factory()->for($show)->primary()->create(['title' => 'Love, Death & Robots']);
    $otherShow = Show::factory()->create();
    ShowTitle::factory()->for($otherShow)->primary()->create(['title' => 'Love']);

    getJson('/api/v1/shows?filter[title]='.urlencode('eq:Love, Death & Robots'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $show->id);
});

test('multiple filter values can be passed as an array', function () {
    actingAsAdmin();

    $action = Category::factory()->create(['value' => 'action']);
    $comedy = Category::factory()->create(['value' => 'comedy']);

    $both = Show::factory()->create();
    $both->categories()->attach([$action->id, $comedy->id]);
    $actionOnly = Show::factory()->create();
    $actionOnly->categories()->attach($action->id);

    getJson('/api/v1/shows?filter[categories.value][]=action&filter[categories.value][]=comedy')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $both->id);
});

test('shows can exclude category values', function () {
    actingAsAdmin();

    $horror = Category::factory()->create(['value' => 'horror']);
    $romance = Category::factory()->create(['value' => 'romance']);
    $action = Category::factory()->create(['value' => 'action']);

    $horrorAction = Show::factory()->create();
    $horrorAction->categories()->attach([$horror->id, $action->id]);
    $romanceShow = Show::factory()->create();
    $romanceShow->categories()->attach($romance->id);
    $actionOnly = Show::factory()->create();
    $actionOnly->categories()->attach($action->id);
    $uncategorized = Show::factory()->create();

    getJson('/api/v1/shows?filter[exclude_categories]=horror&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$romanceShow->id, $actionOnly->id, $uncategorized->id]);

    getJson('/api/v1/shows?filter[exclude_categories][]=horror&filter[exclude_categories][]=romance&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$actionOnly->id, $uncategorized->id]);

    getJson('/api/v1/shows?filter[categories.value]=action&filter[exclude_categories]=horror')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$actionOnly->id]);
});

test('shows can be sorted by primary title', function () {
    actingAsAdmin();

    $banana = Show::factory()->create();
    ShowTitle::factory()->for($banana)->primary()->create(['title' => 'banana']);
    ShowTitle::factory()->for($banana)->create(['title' => 'Aaa alternative']);
    $apple = Show::factory()->create();
    ShowTitle::factory()->for($apple)->primary()->create(['title' => 'Apple']);
    $cherry = Show::factory()->create();
    ShowTitle::factory()->for($cherry)->primary()->create(['title' => 'Cherry']);
    $untitled = Show::factory()->create();

    getJson('/api/v1/shows?sort=title')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$apple->id, $banana->id, $cherry->id, $untitled->id]);

    getJson('/api/v1/shows?sort=-title')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$cherry->id, $banana->id, $apple->id, $untitled->id]);
});

test('listing shows looks up the video base path only once', function () {
    actingAsAdmin();

    Show::factory()->count(3)->create()->each(function (Show $show): void {
        ShowTitle::factory()->for($show)->primary()->create();
        ShowEntry::factory()->for($show)->count(2)->create()->each(
            fn (ShowEntry $entry) => Episode::factory()->for($entry, 'entry')->count(2)->create()
        );
    });

    DB::enableQueryLog();

    getJson('/api/v1/shows')->assertOk();

    $settingQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], '"settings"'))
        ->count();

    expect($settingQueries)->toBe(1);
});

test('bulk show deletion keeps video files when the transaction rolls back', function () {
    $videoBasePath = videoBasePathForTests();
    Setting::query()->whereKey('video_base_path')->update(['value' => $videoBasePath]);

    $deletedShow = Show::factory()->create();
    $failingShow = Show::factory()->create();
    $entry = ShowEntry::factory()->for($deletedShow)->create(['name' => 'Season 1']);
    $episode = Episode::factory()->for($entry, 'entry')->create([
        'name' => 'Episode 1',
        'filename' => 'episode_1.mkv',
    ]);

    $videoPath = ResolveEpisodeVideoPathAction::run($episode);
    File::ensureDirectoryExists(dirname($videoPath));
    File::put($videoPath, 'video');

    Show::deleting(function (Show $show) use ($failingShow): void {
        if ($show->is($failingShow)) {
            throw new RuntimeException('Simulated failure');
        }
    });

    expect(fn () => app(DeleteShowsAction::class)->handle([$deletedShow->id, $failingShow->id]))
        ->toThrow(RuntimeException::class);

    expect(File::exists($videoPath))->toBeTrue();
    assertDatabaseHas('shows', ['id' => $deletedShow->id]);

    File::deleteDirectory($videoBasePath);
});

test('bulk deleting shows removes their video files and keeps other shows files', function () {
    actingAsAdmin();
    $base = useTestVideoBasePath();

    [$firstShow, $secondShow, $keptShow] = Show::factory()->count(3)->create()->all();
    $paths = collect([$firstShow, $secondShow, $keptShow])->mapWithKeys(function (Show $show) use ($base): array {
        $entry = ShowEntry::factory()->for($show)->create(['name' => 'Season 1']);
        $episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep1.mkv']);

        return [$show->id => putVideoFile("$base/{$show->id}_{$show->id}/{$entry->id}_Season 1/{$episode->id}_Episode 1.mkv")];
    });

    deleteJson('/api/v1/shows', ['ids' => [$firstShow->id, $secondShow->id]])->assertOk();

    expect(File::exists($paths[$firstShow->id]))->toBeFalse();
    expect(File::exists($paths[$secondShow->id]))->toBeFalse();
    expect(File::exists($paths[$keptShow->id]))->toBeTrue();
});
