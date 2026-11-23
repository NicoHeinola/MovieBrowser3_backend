<?php

use App\Models\User\User;
use App\Services\Mal\Requests\SearchAnimeRequest;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    Saloon::fake([]);
    config(['mal.base_url' => 'https://mal.test']);
});

/**
 * A saved MyAnimeList search answer for "spy x" (three Spy x Family seasons), optionally changed before it's sent.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>|null  $change
 * @return array<string, mixed>
 */
function malSearchAnswer(?callable $change = null): array
{
    $answer = json_decode(file_get_contents(base_path('tests/Fixtures/mal/search.json')), true);

    return $change !== null ? $change($answer) : $answer;
}

test('MAL anime search requires an admin', function () {
    getJson('/api/v1/mal-anime?filter[search]=spy')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/mal-anime?filter[search]=spy')->assertForbidden();
    Saloon::assertNothingSent();
});

test('an admin gets the matching anime', function () {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => MockResponse::make(malSearchAnswer())]);

    getJson('/api/v1/mal-anime?filter[search]=spy x')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.1', [
            'id' => 50265,
            'title' => 'Spy x Family',
            'url' => 'https://myanimelist.net/anime/50265/Spy_x_Family',
            'image_url' => 'https://cdn.myanimelist.net/r/116x180/images/anime/1441/122795.jpg?s=d29759cb0589c6ef9b7edf698f72367b',
            'media_type' => 'TV',
            'start_year' => 2022,
            'status' => 'Finished Airing',
            'score' => '8.40',
        ]);

    $sent = saloonSent(SearchAnimeRequest::class)->sole()['request'];
    expect($sent->getUrl())->toBe('https://mal.test/search/prefix.json')
        ->and($sent->query()->all())->toMatchArray(['type' => 'anime', 'keyword' => 'spy x']);
});

test('details MAL leaves out come back empty, and items without an id, name or web link are skipped', function () {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => MockResponse::make(malSearchAnswer(function (array $answer): array {
        $items = &$answer['categories'][0]['items'];
        // Not aired yet: no score, year or image
        $items[0]['payload'] = ['media_type' => 'TV', 'start_year' => 0, 'score' => 'N/A', 'status' => 'Not yet aired'];
        unset($items[0]['image_url']);
        unset($items[1]['name']);
        $items[2]['id'] = 'not a number';
        $items[] = [...$items[0], 'id' => 1, 'url' => 'javascript:alert(1)'];

        return $answer;
    }))]);

    getJson('/api/v1/mal-anime?filter[search]=spy x')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.image_url', null)
        ->assertJsonPath('data.0.start_year', null)
        ->assertJsonPath('data.0.score', null)
        ->assertJsonPath('data.0.status', 'Not yet aired');
});

test('a search MAL has nothing for gives no anime', function () {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => MockResponse::make(['categories' => [['type' => 'anime', 'items' => []]]])]);

    getJson('/api/v1/mal-anime?filter[search]=zzzzqqq')->assertOk()->assertJsonCount(0, 'data');
});

test('when MAL can not answer the search is unavailable', function (callable $answer) {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => $answer()]);

    getJson('/api/v1/mal-anime?filter[search]=spy')
        ->assertStatus(502)
        ->assertExactJson(['code' => 'mal_unavailable', 'params' => []]);
})->with([
    'no connection' => fn () => fn () => saloonConnectionRefused(),
    'server error' => fn () => fn () => MockResponse::make('error code: 502', 502),
    'too many requests' => fn () => fn () => MockResponse::make('', 429),
    'unexpected answer' => fn () => fn () => MockResponse::make('<html>Just a moment...</html>'),
]);

test('a repeated search is answered from the cache, ignoring case and spacing', function () {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => MockResponse::make(malSearchAnswer())]);

    getJson('/api/v1/mal-anime?filter[search]=spy x')->assertOk();
    getJson('/api/v1/mal-anime?filter[search]=%20Spy%20%20X%20')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.1.start_year', 2022);

    Saloon::assertSentCount(1);
});

test('a cached search from before an update changed its shape is fetched again', function () {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => MockResponse::make(malSearchAnswer())]);
    Cache::put('mal.anime.'.hash('xxh128', 'spy x'), [['id' => 1]], 60);

    getJson('/api/v1/mal-anime?filter[search]=spy x')->assertOk()->assertJsonCount(3, 'data');

    Saloon::assertSentCount(1);
});

test('a failed search is not cached', function () {
    actingAsAdmin();
    Saloon::fake(['mal.test/*' => saloonSequence([MockResponse::make('error code: 502', 502), MockResponse::make(malSearchAnswer())])]);

    getJson('/api/v1/mal-anime?filter[search]=spy')->assertStatus(502);
    getJson('/api/v1/mal-anime?filter[search]=spy')->assertOk()->assertJsonCount(3, 'data');
});

test('MAL anime search validates its query', function (string $query, string $field) {
    actingAsAdmin();

    getJson("/api/v1/mal-anime?{$query}")->assertUnprocessable()->assertJsonValidationErrors([$field]);
    Saloon::assertNothingSent();
})->with([
    'no search' => ['', 'filter'],
    'empty search' => ['filter[search]=', 'filter.search'],
    'search too long' => ['filter[search]='.str_repeat('a', 256), 'filter.search'],
]);
