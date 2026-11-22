<?php

use App\Models\Category\Category;
use App\Models\User\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['nekobt.base_url' => 'https://nekobt.test', 'mal.base_url' => 'https://mal.test']);
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

/**
 * A saved, trimmed MyAnimeList anime page, optionally changed before it's sent.
 *
 * @param  int  $id  `16498` (Shingeki no Kyojin: English title and synonyms) or `50265` (Spy x Family: neither)
 * @param  callable(string): string|null  $change
 */
function malAnimePage(int $id, ?callable $change = null): string
{
    $page = file_get_contents(base_path("tests/Fixtures/mal/anime-{$id}.html"));

    return $change !== null ? $change($page) : $page;
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

test('a MAL anime page becomes a show template', function () {
    actingAsAdmin();
    $action = Category::factory()->create(['name' => 'Action', 'value' => 'action']);
    Http::fake(['mal.test/anime/16498' => Http::response(malAnimePage(16498))]);

    getJson(showTemplateUrl('https://mal.test/anime/16498/Shingeki_no_Kyojin'))
        ->assertOk()
        ->assertJsonPath('source_url', 'https://mal.test/anime/16498/Shingeki_no_Kyojin')
        // The page's title, English and the synonyms; the Japanese one isn't in Latin script
        ->assertJsonPath('titles', ['Shingeki no Kyojin', 'Attack on Titan', 'AoT', 'SnK'])
        ->assertJsonPath('description', fn (string $description): bool => str_starts_with($description, 'Centuries ago, mankind was slaughtered')
            && str_ends_with($description, 'before the last walls are breached.')
            && !str_contains($description, 'MAL Rewrite')
            // The fixture's indentation is source formatting, not text
            && !str_contains($description, '  '))
        ->assertJsonPath('banner_url', null)
        ->assertJsonPath('card_image_url', 'https://cdn.myanimelist.net/images/anime/10/47347l.jpg')
        ->assertJsonPath('preview_url', null)
        ->assertJsonPath('categories.0.id', $action->id)
        // Genres, themes and the demographic
        ->assertJsonPath('unmatched_genres', ['Award Winning', 'Drama', 'Suspense', 'Gore', 'Military', 'Survival', 'Shounen']);
});

test('a MAL anime page without an English title or synonyms keeps its own title, and a Japanese one in Latin script', function () {
    actingAsAdmin();
    Http::fake(['mal.test/anime/50265' => Http::response(malAnimePage(50265))]);

    getJson(showTemplateUrl('https://www.mal.test/anime/50265'))
        ->assertOk()
        ->assertJsonPath('titles', ['Spy x Family', 'SPY×FAMILY'])
        ->assertJsonPath('card_image_url', 'https://cdn.myanimelist.net/images/anime/1441/122795l.jpg');
});

test('a MAL synopsis keeps its paragraphs, and a missing one stays empty', function () {
    actingAsAdmin();
    Http::fake(['mal.test/anime/50265' => Http::sequence()
        ->push(malAnimePage(50265, fn (string $page): string => preg_replace(
            '#<p itemprop="description">.*?</p>#s',
            "<p itemprop=\"description\">First part.<br />\n<br />\nSecond &amp; last part.<br />\n<br />\n(Source: Somewhere)</p>",
            $page,
        )))
        ->push(malAnimePage(50265, fn (string $page): string => preg_replace(
            '#<p itemprop="description">.*?</p>#s',
            '<p itemprop="description">No synopsis information has been added to this title.</p>',
            $page,
        ))),
    ]);

    getJson(showTemplateUrl('https://mal.test/anime/50265'))
        ->assertOk()
        ->assertJsonPath('description', "First part.\n\nSecond & last part.\n\n(Source: Somewhere)");
    getJson(showTemplateUrl('https://mal.test/anime/50265'))->assertOk()->assertJsonPath('description', null);
});

test('links MAL does not read as an anime page are turned away', function (string $url) {
    actingAsAdmin();

    getJson(showTemplateUrl($url))
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0.code', 'unsupported_show_template_url');
    Http::assertNothingSent();
})->with([
    'a manga page' => ['https://mal.test/manga/23390/Shingeki_no_Kyojin'],
    'the search page' => ['https://mal.test/search/all?q=spy'],
    'an anime without an id' => ['https://mal.test/anime/season'],
]);

test('an anime page MAL does not have is not found', function () {
    actingAsAdmin();
    Http::fake(['mal.test/*' => Http::response('<html>404</html>', 404)]);

    getJson(showTemplateUrl('https://mal.test/anime/99999999'))
        ->assertNotFound()
        ->assertExactJson(['code' => 'show_template_not_found', 'params' => []]);
});

test('when MAL can not answer the template is unavailable', function (callable $answer) {
    actingAsAdmin();
    Http::fake(['mal.test/*' => $answer()]);

    getJson(showTemplateUrl('https://mal.test/anime/16498'))
        ->assertStatus(502)
        ->assertExactJson(['code' => 'show_template_unavailable', 'params' => []]);
})->with([
    'no connection' => fn () => fn () => Http::failedConnection(),
    'server error' => fn () => fn () => Http::response('error code: 502', 502),
    'a page without a title' => fn () => fn () => Http::response('<html><body>Just a moment...</body></html>'),
]);
