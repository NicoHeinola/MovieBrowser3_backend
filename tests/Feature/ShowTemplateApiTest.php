<?php

use App\Models\Category\Category;
use App\Models\User\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['nekobt.base_url' => 'https://nekobt.test']);
});

/**
 * A saved nekoBT media answer, optionally changed before it's sent.
 *
 * @param  string  $kind  `series` (Saga of Tanya the Evil, with specials) or `movie`
 * @param  callable(array<string, mixed>): array<string, mixed>|null  $change
 * @return array<string, mixed>
 */
function nekoBtMediaAnswer(string $kind, ?callable $change = null): array
{
    $answer = json_decode(file_get_contents(base_path("tests/Fixtures/nekobt/media-{$kind}.json")), true);

    return $change !== null ? $change($answer) : $answer;
}

function showTemplateUrl(string $url): string
{
    return '/api/v1/show-template?'.http_build_query(['url' => $url]);
}

test('show templates require an admin', function () {
    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))->assertForbidden();
    Http::assertNothingSent();
});

test('a nekoBT series page becomes a show template', function () {
    actingAsAdmin();
    Http::fake(['nekobt.test/api/v1/media/s1453' => Http::response(nekoBtMediaAnswer('series'))]);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))
        ->assertOk()
        ->assertJsonPath('source_url', 'https://nekobt.test/media/s1453')
        // The English title first, then romaji and the alternate titles, each once
        ->assertJsonPath('titles', ['Saga of Tanya the Evil', 'Youjo Senki', 'Youjo Senki S2', 'Youjo Senki II'])
        ->assertJsonPath('description', fn (string $description): bool => str_starts_with($description, 'The novel series centers'))
        ->assertJsonPath('banner_url', 'https://s4.anilist.co/file/anilistcdn/media/anime/banner/21613-FGAqKSTvfxuA.png')
        ->assertJsonPath('card_image_url', 'https://s4.anilist.co/file/anilistcdn/media/anime/cover/large/bx21613-qT3NiwYP5dYc.png')
        ->assertJsonPath('preview_url', null)
        ->assertJsonMissingPath('entries');
});

test('a nekoBT movie page becomes a show template too, keeping only Latin-script titles', function () {
    actingAsAdmin();
    Http::fake(['nekobt.test/api/v1/media/m134' => Http::response(nekoBtMediaAnswer('movie'))]);

    getJson(showTemplateUrl('https://nekobt.test/media/m134?ref=search'))
        ->assertOk()
        ->assertJsonPath('titles', [
            'Saga of Tanya the Evil - the Movie -',
            'Youjo Senki Movie',
            'Gekijouban Youjo Senki',
            'Yōjo Senki Movie',
            'Saga of Tanya the Evil: La película',
        ]);
});

test('genres match categories by name or value, ignoring case, and the rest are listed', function () {
    actingAsAdmin();
    $action = Category::factory()->create(['name' => 'action', 'value' => 'action']);
    $scienceFiction = Category::factory()->create(['name' => 'Science fiction', 'value' => 'sci-fi']);
    Category::factory()->create(['name' => 'Romance', 'value' => 'romance']);
    Http::fake(['nekobt.test/*' => Http::response(nekoBtMediaAnswer('series', function (array $answer): array {
        $answer['data']['genres'] = ['Action', 'Sci-Fi', 'Fantasy'];

        return $answer;
    }))]);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))
        ->assertOk()
        ->assertJsonPath('categories.*.id', [$action->id, $scienceFiction->id])
        ->assertJsonPath('unmatched_genres', ['Fantasy']);
});

test('missing images and descriptions fall back, then stay empty', function () {
    actingAsAdmin();
    Http::fake(['nekobt.test/*' => Http::response(nekoBtMediaAnswer('series', function (array $answer): array {
        unset($answer['data']['anilist']);
        $answer['data']['overview'] = null;

        return $answer;
    }))]);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))
        ->assertOk()
        // nekoBT's own image is a poster, so it can stand in for the card
        ->assertJsonPath('card_image_url', 'https://artworks.thetvdb.com/banners/v4/series/315500/posters/60d1cf343f374.jpg')
        ->assertJsonPath('banner_url', null)
        ->assertJsonPath('description', null)
        ->assertJsonPath('titles', ['Saga of Tanya the Evil', 'Youjo Senki', 'Youjo Senki S2', 'Youjo Senki II']);
});

test('without an overview, AniList\'s HTML description becomes plain text', function () {
    actingAsAdmin();
    Http::fake(['nekobt.test/*' => Http::response(nekoBtMediaAnswer('series', function (array $answer): array {
        $answer['data']['overview'] = '';
        $answer['data']['anilist']['primary']['description'] = '<i>Tanya</i> &amp; her battalion<br>fight on.';

        return $answer;
    }))]);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))
        ->assertOk()
        ->assertJsonPath('description', "Tanya & her battalion\nfight on.");
});

test('a show with no title in Latin script keeps its main title', function () {
    actingAsAdmin();
    Http::fake(['nekobt.test/*' => Http::response(nekoBtMediaAnswer('series', function (array $answer): array {
        $answer['data']['title'] = '幼女戦記';
        $answer['data']['alternate_titles'] = ['Колдунья в погонах'];
        unset($answer['data']['anilist']);

        return $answer;
    }))]);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))->assertOk()->assertJsonPath('titles', ['幼女戦記']);
});

test('a link no source reads is turned away', function (string $url, string $code) {
    actingAsAdmin();

    getJson(showTemplateUrl($url))
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0.code', $code);
    Http::assertNothingSent();
})->with([
    'another site' => ['https://anilist.co/anime/21613', 'unsupported_show_template_url'],
    'a nekoBT torrent page' => ['https://nekobt.test/torrents/7338487288586', 'unsupported_show_template_url'],
    'not a link' => ['nekobt media s1453', 'url'],
]);

test('a request without a link is turned away', function () {
    actingAsAdmin();

    getJson('/api/v1/show-template')->assertUnprocessable()->assertJsonValidationErrors(['url']);
});

test('a media page nekoBT does not have is not found', function () {
    actingAsAdmin();
    Http::fake(['nekobt.test/*' => Http::response(['error' => true, 'message' => 'Media not found.'], 404)]);

    getJson(showTemplateUrl('https://nekobt.test/media/s99999999'))
        ->assertNotFound()
        ->assertExactJson(['code' => 'show_template_not_found', 'params' => []]);
});

test('when nekoBT can not answer the template is unavailable', function (callable $answer) {
    actingAsAdmin();
    Http::fake(['nekobt.test/*' => $answer()]);

    getJson(showTemplateUrl('https://nekobt.test/media/s1453'))
        ->assertStatus(502)
        ->assertExactJson(['code' => 'show_template_unavailable', 'params' => []]);
})->with([
    'no connection' => fn () => fn () => Http::failedConnection(),
    'server error' => fn () => fn () => Http::response('error code: 502', 502),
    'error answer' => fn () => fn () => Http::response(['error' => true, 'message' => 'Something broke.'], 400),
    'unexpected answer' => fn () => fn () => Http::response('<html>Just a moment...</html>'),
]);
