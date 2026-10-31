<?php

use App\Models\User\User;
use App\Models\Website\Website;
use App\Models\WebsiteTag\WebsiteTag;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('website endpoints require authentication', function () {
    $website = Website::factory()->create();

    getJson('/api/v1/websites')->assertUnauthorized();
    getJson("/api/v1/websites/{$website->id}")->assertUnauthorized();
    postJson('/api/v1/websites', ['url' => 'https://example.com'])->assertUnauthorized();
    patchJson("/api/v1/websites/{$website->id}", ['title' => 'x'])->assertUnauthorized();
    deleteJson("/api/v1/websites/{$website->id}")->assertUnauthorized();
});

test('website endpoints require admin', function () {
    $user = User::factory()->create();
    withToken($user->createToken('test-token')->plainTextToken);

    $website = Website::factory()->create();

    getJson('/api/v1/websites')->assertForbidden();
    getJson("/api/v1/websites/{$website->id}")->assertForbidden();
    postJson('/api/v1/websites', ['url' => 'https://example.com'])->assertForbidden();
    patchJson("/api/v1/websites/{$website->id}", ['title' => 'x'])->assertForbidden();
    deleteJson("/api/v1/websites/{$website->id}")->assertForbidden();
});

test('an admin can list and view websites', function () {
    actingAsAdmin();

    $tag = WebsiteTag::factory()->create(['name' => 'tools']);
    $website = Website::factory()->create(['url' => 'https://a.example.com']);
    $website->tags()->attach($tag);
    Website::factory()->create();

    getJson('/api/v1/websites')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.tags.0.id', $tag->id)
        ->assertJsonPath('data.0.tags.0.name', 'tools');

    getJson("/api/v1/websites/{$website->id}")
        ->assertOk()
        ->assertJsonPath('id', $website->id)
        ->assertJsonPath('url', 'https://a.example.com')
        ->assertJsonPath('tags', [['id' => $tag->id, 'name' => 'tools']]);
});

test('an admin can create a website with tags', function () {
    actingAsAdmin();

    $tools = WebsiteTag::factory()->create(['name' => 'tools']);
    $subtitles = WebsiteTag::factory()->create(['name' => 'subtitles']);

    $response = postJson('/api/v1/websites', [
        'url' => 'https://example.com',
        'title' => 'Example',
        'description' => 'A useful site',
        'tag_ids' => [$tools->id, $subtitles->id],
    ])
        ->assertCreated()
        ->assertJsonPath('url', 'https://example.com')
        ->assertJsonPath('title', 'Example')
        ->assertJsonPath('tags.*.name', ['subtitles', 'tools']);

    assertDatabaseHas('website_website_tag', ['website_id' => $response->json('id'), 'website_tag_id' => $tools->id]);
});

test('an admin can update a website and replace its tags', function () {
    actingAsAdmin();

    $old = WebsiteTag::factory()->create(['name' => 'old']);
    $keep = WebsiteTag::factory()->create(['name' => 'keep']);
    $added = WebsiteTag::factory()->create(['name' => 'added']);
    $website = Website::factory()->create(['title' => 'Old', 'description' => 'Keep me']);
    $website->tags()->attach([$old->id, $keep->id]);

    patchJson("/api/v1/websites/{$website->id}", [
        'title' => 'New',
        'tag_ids' => [$keep->id, $added->id],
    ])
        ->assertOk()
        ->assertJsonPath('title', 'New')
        ->assertJsonPath('description', 'Keep me')
        ->assertJsonPath('tags.*.name', ['added', 'keep']);

    assertDatabaseMissing('website_website_tag', ['website_id' => $website->id, 'website_tag_id' => $old->id]);
    assertDatabaseHas('website_tags', ['id' => $old->id]);

    patchJson("/api/v1/websites/{$website->id}", ['tag_ids' => []])
        ->assertOk()
        ->assertJsonPath('tags', []);
});

test('a partial website update keeps omitted fields and tags', function () {
    actingAsAdmin();

    $website = Website::factory()->create(['title' => 'Old', 'icon' => 'globe']);
    $website->tags()->attach(WebsiteTag::factory()->create(['name' => 'keep']));

    patchJson("/api/v1/websites/{$website->id}", ['title' => null])
        ->assertOk()
        ->assertJsonPath('title', null)
        ->assertJsonPath('icon', 'globe')
        ->assertJsonPath('tags.*.name', ['keep']);
});

test('an admin can delete a website and keep its tags', function () {
    actingAsAdmin();

    $tag = WebsiteTag::factory()->create();
    $website = Website::factory()->create();
    $website->tags()->attach($tag);

    deleteJson("/api/v1/websites/{$website->id}")->assertNoContent();

    assertDatabaseMissing('websites', ['id' => $website->id]);
    assertDatabaseMissing('website_website_tag', ['website_id' => $website->id]);
    assertDatabaseHas('website_tags', ['id' => $tag->id]);
});

test('websites can be filtered by tags and sorted', function () {
    actingAsAdmin();

    $tools = WebsiteTag::factory()->create(['name' => 'tools']);
    $anime = WebsiteTag::factory()->create(['name' => 'anime']);
    $both = Website::factory()->create(['title' => 'B']);
    $both->tags()->attach([$tools->id, $anime->id]);
    $toolsOnly = Website::factory()->create(['title' => 'C']);
    $toolsOnly->tags()->attach($tools);
    $untagged = Website::factory()->create(['title' => 'A']);

    getJson('/api/v1/websites?filter[tags.name]=anime')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$both->id]);

    getJson("/api/v1/websites?filter[tags.id]={$tools->id}&sort=id")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$both->id, $toolsOnly->id]);

    getJson("/api/v1/websites?filter[tags.id][]={$tools->id}&filter[tags.id][]={$anime->id}")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$both->id]);

    getJson("/api/v1/websites?filter[exclude_tags]={$anime->id}&sort=id")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$toolsOnly->id, $untagged->id]);

    getJson("/api/v1/websites?filter[exclude_tags][]={$anime->id}&filter[exclude_tags][]={$tools->id}")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$untagged->id]);

    getJson('/api/v1/websites?filter[tags.id]=abc')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    getJson('/api/v1/websites?filter[exclude_tags]=abc')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    getJson('/api/v1/websites?sort=title')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['A', 'B', 'C']);
});

test('store website validates input', function () {
    actingAsAdmin();

    Website::factory()->create(['url' => 'https://example.com']);
    $tag = WebsiteTag::factory()->create();

    postJson('/api/v1/websites', [])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    postJson('/api/v1/websites', ['url' => 'not a url'])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    postJson('/api/v1/websites', ['url' => 'https://example.com'])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    postJson('/api/v1/websites', ['url' => 'https://new.example.com', 'tag_ids' => [999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tag_ids.0']);
    postJson('/api/v1/websites', ['url' => 'https://new.example.com', 'tag_ids' => [$tag->id, $tag->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tag_ids.0', 'tag_ids.1']);
});

test('update website validates input', function () {
    actingAsAdmin();

    Website::factory()->create(['url' => 'https://taken.example.com']);
    $website = Website::factory()->create(['url' => 'https://own.example.com']);

    patchJson("/api/v1/websites/{$website->id}", ['url' => 'https://taken.example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
    patchJson("/api/v1/websites/{$website->id}", ['url' => 'not a url'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
    patchJson("/api/v1/websites/{$website->id}", ['tag_ids' => [999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tag_ids.0']);

    patchJson("/api/v1/websites/{$website->id}", ['url' => 'https://own.example.com'])
        ->assertOk()
        ->assertJsonPath('url', 'https://own.example.com');
});

test('a website url can be up to 2048 characters', function () {
    actingAsAdmin();

    $url = 'https://example.com/'.str_repeat('a', 2028);

    postJson('/api/v1/websites', ['url' => $url])->assertCreated();
    postJson('/api/v1/websites', ['url' => $url.'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

test('websites can be searched across title, url, description and tags', function () {
    actingAsAdmin();

    $subs = WebsiteTag::factory()->create(['name' => 'Subtitles']);
    $byTitle = Website::factory()->create(['title' => 'Anime Tracker', 'url' => 'https://a.example.com', 'description' => null]);
    $byUrl = Website::factory()->create(['title' => 'Other', 'url' => 'https://ANIME.example.com', 'description' => null]);
    $byDescription = Website::factory()->create(['title' => 'Notes', 'url' => 'https://c.example.com', 'description' => 'Find anime releases']);
    $byTag = Website::factory()->create(['title' => 'Subs', 'url' => 'https://d.example.com', 'description' => null]);
    $byTag->tags()->attach($subs);
    $unrelated = Website::factory()->create(['title' => 'Weather', 'url' => 'https://e.example.com', 'description' => null]);

    getJson('/api/v1/websites?filter[search]=anime&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$byTitle->id, $byUrl->id, $byDescription->id]);

    getJson('/api/v1/websites?filter[search]=SUBTITLE')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$byTag->id]);

    getJson('/api/v1/websites?filter[search]='.urlencode('  anime   tracker ').'')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$byTitle->id]);

    getJson('/api/v1/websites?filter[search]='.urlencode('anime weather'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    getJson('/api/v1/websites?filter[search]=%20%20')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    getJson('/api/v1/websites?filter[search]=')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    expect($unrelated->exists)->toBeTrue();
});

test('website search treats like wildcards literally', function () {
    actingAsAdmin();

    $percent = Website::factory()->create(['title' => '100% free', 'description' => null]);
    Website::factory()->create(['title' => '1000 free', 'description' => null]);
    $underscore = Website::factory()->create(['title' => 'snake_case', 'description' => null]);
    Website::factory()->create(['title' => 'snakeXcase', 'description' => null]);

    getJson('/api/v1/websites?filter[search]='.urlencode('100%'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$percent->id]);

    getJson('/api/v1/websites?filter[search]=snake_case')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$underscore->id]);
});

test('website search combines with tag filters, sorts and pagination', function () {
    actingAsAdmin();

    $tools = WebsiteTag::factory()->create(['name' => 'tools']);
    $nsfw = WebsiteTag::factory()->create(['name' => 'nsfw']);
    $first = Website::factory()->create(['title' => 'Anime B']);
    $first->tags()->attach($tools);
    $second = Website::factory()->create(['title' => 'Anime A']);
    $second->tags()->attach($tools);
    $excluded = Website::factory()->create(['title' => 'Anime C']);
    $excluded->tags()->attach([$tools->id, $nsfw->id]);
    Website::factory()->create(['title' => 'Anime untagged']);

    getJson("/api/v1/websites?filter[search]=anime&filter[tags.id]={$tools->id}&filter[exclude_tags]={$nsfw->id}&sort=title")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$second->id, $first->id]);

    getJson("/api/v1/websites?filter[search]=anime&filter[tags.id]={$tools->id}&sort=title&page[size]=1&page[number]=2")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$first->id])
        ->assertJsonPath('meta.total', 3);
});

test('websites sorted by title fall back to the hostname when untitled', function () {
    actingAsAdmin();

    $apple = Website::factory()->create(['title' => 'Apple', 'url' => 'https://y.example.com']);
    $github = Website::factory()->create(['title' => null, 'url' => 'https://WWW.github.com/x']);
    $banana = Website::factory()->create(['title' => 'banana', 'url' => 'https://z.example.com']);
    $cherry = Website::factory()->create(['title' => null, 'url' => 'http://cherry.example.com']);
    $delta = Website::factory()->create(['title' => '', 'url' => 'https://Delta.example.com']);
    $hulu = Website::factory()->create(['title' => 'hulu', 'url' => 'https://a.example.com']);

    getJson('/api/v1/websites?sort=title')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$apple->id, $banana->id, $cherry->id, $delta->id, $github->id, $hulu->id]);

    getJson('/api/v1/websites?sort=-title')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$hulu->id, $github->id, $delta->id, $cherry->id, $banana->id, $apple->id]);
});
