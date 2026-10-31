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

test('website tag endpoints require authentication', function () {
    $tag = WebsiteTag::factory()->create();

    getJson('/api/v1/website-tags')->assertUnauthorized();
    getJson("/api/v1/website-tags/{$tag->id}")->assertUnauthorized();
    postJson('/api/v1/website-tags', ['name' => 'tools'])->assertUnauthorized();
    patchJson("/api/v1/website-tags/{$tag->id}", ['name' => 'tools'])->assertUnauthorized();
    deleteJson("/api/v1/website-tags/{$tag->id}")->assertUnauthorized();
});

test('website tag endpoints require admin', function () {
    $user = User::factory()->create();
    withToken($user->createToken('test-token')->plainTextToken);

    $tag = WebsiteTag::factory()->create();

    getJson('/api/v1/website-tags')->assertForbidden();
    getJson("/api/v1/website-tags/{$tag->id}")->assertForbidden();
    postJson('/api/v1/website-tags', ['name' => 'tools'])->assertForbidden();
    patchJson("/api/v1/website-tags/{$tag->id}", ['name' => 'tools'])->assertForbidden();
    deleteJson("/api/v1/website-tags/{$tag->id}")->assertForbidden();
});

test('an admin can list and view website tags with their website counts', function () {
    actingAsAdmin();

    $tools = WebsiteTag::factory()->create(['name' => 'tools']);
    $unused = WebsiteTag::factory()->create(['name' => 'unused']);
    Website::factory()->count(2)->create()->each(fn (Website $website) => $website->tags()->attach($tools));

    getJson('/api/v1/website-tags?sort=name')
        ->assertOk()
        ->assertJsonPath('data', [
            ['id' => $tools->id, 'name' => 'tools', 'websites_count' => 2],
            ['id' => $unused->id, 'name' => 'unused', 'websites_count' => 0],
        ]);

    getJson("/api/v1/website-tags/{$tools->id}")
        ->assertOk()
        ->assertJsonPath('name', 'tools')
        ->assertJsonPath('websites_count', 2);
});

test('website tags can be filtered by name and sorted', function () {
    actingAsAdmin();

    $subtitles = WebsiteTag::factory()->create(['name' => 'subtitles']);
    WebsiteTag::factory()->create(['name' => 'tools']);
    $anime = WebsiteTag::factory()->create(['name' => 'anime subs']);

    getJson('/api/v1/website-tags?filter[name]=like:sub&sort=name')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$anime->id, $subtitles->id]);

    getJson('/api/v1/website-tags?sort=-name')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['tools', 'subtitles', 'anime subs']);
});

test('website tags can be searched by every word of the name ignoring case', function () {
    actingAsAdmin();

    $docs = WebsiteTag::factory()->create(['name' => 'Docs']);
    $apiDocs = WebsiteTag::factory()->create(['name' => 'API docs']);
    WebsiteTag::factory()->create(['name' => 'tools']);
    $percent = WebsiteTag::factory()->create(['name' => '100% free']);
    WebsiteTag::factory()->create(['name' => '1000 free']);
    $underscore = WebsiteTag::factory()->create(['name' => 'snake_case']);
    WebsiteTag::factory()->create(['name' => 'snakeXcase']);

    getJson('/api/v1/website-tags?filter[search]=doc&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$docs->id, $apiDocs->id]);

    getJson('/api/v1/website-tags?filter[search]='.urlencode(' DOCS  api '))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$apiDocs->id]);

    getJson('/api/v1/website-tags?filter[search]='.urlencode('100%'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$percent->id]);

    getJson('/api/v1/website-tags?filter[search]=snake_case')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$underscore->id]);

    getJson('/api/v1/website-tags?filter[search]=%20')
        ->assertOk()
        ->assertJsonCount(7, 'data');
});

test('an admin can create a website tag', function () {
    actingAsAdmin();

    postJson('/api/v1/website-tags', ['name' => 'Tools'])
        ->assertCreated()
        ->assertJsonPath('name', 'Tools')
        ->assertJsonPath('websites_count', 0);

    assertDatabaseHas('website_tags', ['name' => 'Tools']);
});

test('an admin can rename a website tag', function () {
    actingAsAdmin();

    $tag = WebsiteTag::factory()->create(['name' => 'tools']);

    patchJson("/api/v1/website-tags/{$tag->id}", ['name' => 'Tools'])
        ->assertOk()
        ->assertJsonPath('id', $tag->id)
        ->assertJsonPath('name', 'Tools');
});

test('deleting a website tag detaches it from websites', function () {
    actingAsAdmin();

    $tag = WebsiteTag::factory()->create();
    $website = Website::factory()->create();
    $website->tags()->attach($tag);

    deleteJson("/api/v1/website-tags/{$tag->id}")->assertNoContent();

    assertDatabaseMissing('website_tags', ['id' => $tag->id]);
    assertDatabaseMissing('website_website_tag', ['website_tag_id' => $tag->id]);
    assertDatabaseHas('websites', ['id' => $website->id]);
});

test('website tag names are unique ignoring case', function () {
    actingAsAdmin();

    WebsiteTag::factory()->create(['name' => 'Tools']);
    $other = WebsiteTag::factory()->create(['name' => 'anime']);

    postJson('/api/v1/website-tags', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    postJson('/api/v1/website-tags', ['name' => ['tools']])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    postJson('/api/v1/website-tags', ['name' => 'tools'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    patchJson("/api/v1/website-tags/{$other->id}", ['name' => 'TOOLS'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
});
