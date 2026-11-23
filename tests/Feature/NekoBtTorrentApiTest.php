<?php

use App\Dtos\NekoBt\NekoBtSearchData;
use App\Models\User\User;
use App\Services\NekoBt\Requests\SearchTorrentsRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    Saloon::fake([]);
    config(['nekobt.base_url' => 'https://nekobt.test']);
});

/**
 * A saved nekoBT search answer (three results: a batch and two movies), optionally changed before it's sent.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>|null  $change
 * @return array<string, mixed>
 */
function nekoBtSearchAnswer(?callable $change = null): array
{
    $answer = json_decode(file_get_contents(base_path('tests/Fixtures/nekobt/search.json')), true);

    return $change !== null ? $change($answer) : $answer;
}

test('nekoBT torrent search requires an admin', function () {
    getJson('/api/v1/nekobt-torrents')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/nekobt-torrents')->assertForbidden();
    Saloon::assertNothingSent();
});

test('an admin gets a page of torrents', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    getJson('/api/v1/nekobt-torrents?filter[search]=tanya the evil')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0', [
            'id' => '7338487288586',
            'title' => '[Vodes] Youjo Senki S01 + ONA [BD 1080p HEVC 10bit FLAC] [Dual-Audio] [EngGerSub] | Saga of Tanya the Evil',
            'size_bytes' => 20357714722,
            'uploaded_at' => '2025-11-28T18:46:05.000000Z',
            'seeders' => 3,
            'leechers' => 0,
            'downloads' => 269,
            'comments' => 0,
            'is_batch' => true,
            'uploader' => 'Vodes',
            'groups' => ['Vodes'],
            'audio_languages' => ['ja', 'en'],
            'subtitle_languages' => ['en', 'de'],
            'level' => 'small_scale_fansub',
            'video_source' => 'bd_encode',
            'video_codec' => 'h265',
            'is_original_translation' => false,
            'is_machine_translation' => false,
            'is_hardsub' => false,
            'magnet_url' => 'magnet:?xt=urn:btih:14bc7bf562f9504e30816b6403ab19b302e26ae2&dn=%5BVodes%5D%20Youjo%20Senki%20S01%20%2B%20ONA%20%5BBD%201080p%20HEVC%2010bit%20FLAC%5D%20%5BDual-Audio%5D%20%5BEngGerSub%5D%20%7C%20Saga%20of%20Tanya%20the%20Evil%20%7BTags%3AL2%3BV13%3BC2%3BA%3Dja%2Cen%3BF%3Den%2Cde%3B%7D&tr=https%3A%2F%2Ftracker.nekobt.to%2Fapi%2Ftracker%2Fpublic%2Fannounce&tr=http%3A%2F%2Fnyaa.tracker.wf%3A7777%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce&tr=udp%3A%2F%2Fexodus.desync.com%3A6969%2Fannounce&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce',
            'torrent_url' => 'https://nekobt.test/api/v1/torrents/7338487288586/download?public=true',
            'view_url' => 'https://nekobt.test/torrents/7338487288586',
            'media_url' => 'https://nekobt.test/media/s1453',
        ])
        ->assertJsonPath('data.1.is_batch', false)
        // An anonymous upload hides its uploader
        ->assertJsonPath('data.1.uploader', null)
        ->assertJsonPath('data.1.video_codec', 'h264')
        ->assertJsonPath('data.2.level', 'slight_modifications')
        ->assertJsonPath('data.1.media_url', 'https://nekobt.test/media/m134')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 50)
        // The next link keeps the search, and has only the next page number
        ->assertJsonPath('links.next', fn (?string $next): bool => str_contains((string) $next, 'filter%5Bsearch%5D=tanya')
            && str_contains((string) $next, 'page%5Bnumber%5D=2')
            && !str_contains((string) $next, 'page%5Bnumber%5D=1'));
});

test('the search defaults to the biggest torrents first', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    getJson('/api/v1/nekobt-torrents')->assertOk();

    expect(saloonSentUrls())->toContain('https://nekobt.test/api/v1/torrents/search?query=&sort_by=filesize&limit=50&offset=0');
});

test('the sort and page reach nekoBT in its own words', function (string $sort, string $sortBy) {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    getJson("/api/v1/nekobt-torrents?filter[search]=one piece&sort={$sort}&page[number]=3")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 3);

    expect(saloonSentUrls())->toContain("https://nekobt.test/api/v1/torrents/search?query=one%20piece&sort_by={$sortBy}&limit=50&offset=100");
})->with([
    ['best', 'best'],
    ['-date', 'latest'],
    ['date', 'oldest'],
    ['size', 'filesize_asc'],
    ['-seeders', 'seeders'],
    ['leechers', 'leechers_asc'],
    ['-downloads', 'downloads'],
    ['comments', 'comments_asc'],
]);

test('the filters reach nekoBT in its own words', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    $query = http_build_query([
        'filter' => [
            'batch' => 'true',
            'levels' => ['full_scale_fansub', 'small_scale_fansub'],
            'video_sources' => ['bd_remux', 'bd_encode'],
            'video_codecs' => ['h265'],
            'audio_language' => 'es-419',
            'original_translation' => 'true',
            'machine_translation' => 'false',
            'hardsub' => 'false',
        ],
    ]);

    getJson("/api/v1/nekobt-torrents?{$query}")->assertOk();

    expect(array_map(strval(...), saloonSent(SearchTorrentsRequest::class)->sole()['request']->query()->all()))->toBe([
        'query' => '',
        'sort_by' => 'filesize',
        'limit' => '50',
        'offset' => '0',
        'batch' => 'true',
        'levels' => '3,2',
        'video_type' => '14,13',
        'video_codec' => '2',
        'audio_lang' => 'es-419',
        'otl' => 'true',
        'mtl' => 'false',
        'hardsub' => 'false',
    ]);
});

test('filtered searches are cached apart', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    getJson('/api/v1/nekobt-torrents?filter[batch]=true')->assertOk();
    getJson('/api/v1/nekobt-torrents?filter[batch]=false')->assertOk();
    getJson('/api/v1/nekobt-torrents?filter[batch]=true')->assertOk();

    Saloon::assertSentCount(2);
});

test('the last page has no next link', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        $answer['data']['more'] = false;

        return $answer;
    }))]);

    getJson('/api/v1/nekobt-torrents')->assertOk()->assertJsonPath('links.next', null);
});

test('hidden and deleted uploads are left out', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        $answer['data']['results'][0]['hidden'] = true;
        $answer['data']['results'][1]['deleted'] = 1767019033060;

        return $answer;
    }))]);

    getJson('/api/v1/nekobt-torrents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', '7338516836870');
});

test('an upload without groups takes its group from the title', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        $answer['data']['results'][0]['groups'] = [];
        $answer['data']['results'][1]['groups'] = [];
        $answer['data']['results'][1]['title'] = 'Saga of Tanya the Evil - The Movie';

        return $answer;
    }))]);

    getJson('/api/v1/nekobt-torrents')
        ->assertOk()
        ->assertJsonPath('data.0.groups', ['Vodes'])
        ->assertJsonPath('data.1.groups', []);
});

test('details nekoBT leaves out or numbers it does not know come back empty', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        $answer['data']['results'][0]['level'] = 9;
        $answer['data']['results'][0]['video_type'] = 99;
        unset($answer['data']['results'][0]['video_codec'], $answer['data']['results'][0]['audio_lang']);
        $answer['data']['results'][0]['fsub_lang'] = '';

        return $answer;
    }))]);

    getJson('/api/v1/nekobt-torrents')
        ->assertOk()
        ->assertJsonPath('data.0.level', null)
        ->assertJsonPath('data.0.video_source', null)
        ->assertJsonPath('data.0.video_codec', null)
        ->assertJsonPath('data.0.audio_languages', [])
        ->assertJsonPath('data.0.subtitle_languages', []);
});

test('private uploads without a public magnet are left out', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        $answer['data']['results'][2]['magnet'] = null;
        $answer['data']['results'][2]['private_magnet'] = 'magnet:?xt=urn:btih:private';

        return $answer;
    }))]);

    getJson('/api/v1/nekobt-torrents')->assertOk()->assertJsonCount(2, 'data');
});

test('missing details get defaults, and results missing what every torrent has are skipped', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        unset($answer['data']['more']);
        unset(
            $answer['data']['results'][0]['comment_count'],
            $answer['data']['results'][0]['seeders'],
            $answer['data']['results'][0]['filesize'],
            $answer['data']['results'][0]['batch'],
            $answer['data']['results'][0]['groups'],
            $answer['data']['results'][0]['uploader'],
        );
        unset($answer['data']['results'][1]['title']);
        $answer['data']['results'][2]['uploaded_at'] = null;

        return $answer;
    }))]);
    Log::spy();

    getJson('/api/v1/nekobt-torrents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comments', 0)
        ->assertJsonPath('data.0.seeders', 0)
        ->assertJsonPath('data.0.size_bytes', 0)
        ->assertJsonPath('data.0.is_batch', false)
        ->assertJsonPath('data.0.uploader', null)
        // Without groups, the group comes from the title's prefix
        ->assertJsonPath('data.0.groups', ['Vodes'])
        ->assertJsonPath('links.next', null);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, '2 results'));
});

test('the deepest page has no next link, even when nekoBT has more', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    getJson('/api/v1/nekobt-torrents?page[number]=100')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 100)
        ->assertJsonPath('links.next', null);
});

test('a cached page from before an update changed its shape is fetched again', function (callable $staleCache) {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);
    $key = 'torrents.nekobt.'.hash('xxh128', (string) json_encode(NekoBtSearchData::fromQuery([])->toArray()));

    // A fresh page as the cache holds it, changed the way an update would have outdated it
    getJson('/api/v1/nekobt-torrents')->assertOk();
    Cache::put($key, $staleCache(Cache::get($key)), 600);

    getJson('/api/v1/nekobt-torrents')->assertOk()->assertJsonCount(3, 'data');

    Saloon::assertSentCount(2);
})->with([
    'a missing field' => fn () => fn (array $page): array => ['torrents' => [['id' => '1']], 'page' => 1],
    'a renamed enum case' => fn () => function (array $page): array {
        $page['torrents'][0]['level'] = 'renamed_level';

        return $page;
    },
    'another date format' => fn () => function (array $page): array {
        $page['torrents'][0]['uploadedAt'] = 'yesterday-ish';

        return $page;
    },
]);

test('a torrent without a media page has no media link', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer(function (array $answer): array {
        $answer['data']['results'][0]['media_id'] = null;

        return $answer;
    }))]);

    getJson('/api/v1/nekobt-torrents')->assertOk()->assertJsonPath('data.0.media_url', null);
});

test('when nekoBT can not answer the search is unavailable', function (callable $answer) {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => $answer()]);

    getJson('/api/v1/nekobt-torrents')
        ->assertStatus(502)
        ->assertExactJson(['code' => 'torrent_site_unavailable', 'params' => ['site' => 'nekobt']]);
})->with([
    'no connection' => fn () => fn () => saloonConnectionRefused(),
    'server error' => fn () => fn () => MockResponse::make('error code: 502', 502),
    'error answer' => fn () => fn () => MockResponse::make(['error' => true, 'message' => "Data provided was invalid: 'limit' must be <= 100."], 400),
    'unexpected answer' => fn () => fn () => MockResponse::make('<html>Just a moment...</html>'),
]);

test('a repeated search is answered from the cache', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => MockResponse::make(nekoBtSearchAnswer())]);

    getJson('/api/v1/nekobt-torrents?filter[search]=tanya')->assertOk();
    getJson('/api/v1/nekobt-torrents?filter[search]=tanya')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.uploaded_at', '2025-11-28T18:46:05.000000Z')
        ->assertJsonPath('data.0.level', 'small_scale_fansub')
        ->assertJsonPath('data.0.audio_languages', ['ja', 'en'])
        ->assertJsonPath('links.next', fn (?string $next): bool => $next !== null);

    Saloon::assertSentCount(1);
});

test('a failed search is not cached', function () {
    actingAsAdmin();
    Saloon::fake(['nekobt.test/*' => saloonSequence([MockResponse::make('error code: 502', 502), MockResponse::make(nekoBtSearchAnswer())])]);

    getJson('/api/v1/nekobt-torrents')->assertStatus(502);
    getJson('/api/v1/nekobt-torrents')->assertOk()->assertJsonCount(3, 'data');
});

test('nekoBT torrent search validates its query', function (string $query, string $field) {
    actingAsAdmin();

    getJson("/api/v1/nekobt-torrents?{$query}")->assertUnprocessable()->assertJsonValidationErrors([$field]);
    Saloon::assertNothingSent();
})->with([
    'unknown sort' => ['sort=-title', 'sort'],
    'best has no direction' => ['sort=-best', 'sort'],
    'search too long' => ['filter[search]='.str_repeat('a', 256), 'filter.search'],
    'page zero' => ['page[number]=0', 'page.number'],
    'page too deep' => ['page[number]=101', 'page.number'],
    'batch not true or false' => ['filter[batch]=1', 'filter.batch'],
    'unknown level' => ['filter[levels][]=godlike', 'filter.levels.0'],
    'unknown video source' => ['filter[video_sources][]=betamax', 'filter.video_sources.0'],
    'unknown codec' => ['filter[video_codecs][]=h266', 'filter.video_codecs.0'],
    'language that is no code' => ['filter[audio_language]=English', 'filter.audio_language'],
    'hardsub not true or false' => ['filter[hardsub]=yes', 'filter.hardsub'],
]);
