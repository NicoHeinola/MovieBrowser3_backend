<?php

use App\Models\User\User;
use App\Models\Website\Website;

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

    $website = Website::factory()->create(['url' => 'https://a.example.com']);
    $website->tags()->create(['name' => 'tools']);
    Website::factory()->create();

    getJson('/api/v1/websites')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.tags.0.name', 'tools');

    getJson("/api/v1/websites/{$website->id}")
        ->assertOk()
        ->assertJsonPath('id', $website->id)
        ->assertJsonPath('url', 'https://a.example.com')
        ->assertJsonPath('tags.0.name', 'tools');
});

test('an admin can create a website with tags', function () {
    actingAsAdmin();

    postJson('/api/v1/websites', [
        'url' => 'https://example.com',
        'title' => 'Example',
        'description' => 'A useful site',
        'tags' => ['subtitles', 'tools'],
    ])
        ->assertCreated()
        ->assertJsonPath('url', 'https://example.com')
        ->assertJsonPath('title', 'Example')
        ->assertJsonCount(2, 'tags')
        ->assertJsonPath('tags.0.name', 'subtitles')
        ->assertJsonPath('tags.1.name', 'tools');

    assertDatabaseHas('websites', ['url' => 'https://example.com']);
    assertDatabaseHas('website_tags', ['name' => 'subtitles']);
});

test('an admin can update a website and sync its tags', function () {
    actingAsAdmin();

    $website = Website::factory()->create(['title' => 'Old', 'description' => 'Keep me']);
    $website->tags()->createMany([['name' => 'old'], ['name' => 'keep']]);

    patchJson("/api/v1/websites/{$website->id}", [
        'title' => 'New',
        'tags' => ['keep', 'added'],
    ])
        ->assertOk()
        ->assertJsonPath('title', 'New')
        ->assertJsonPath('description', 'Keep me')
        ->assertJsonCount(2, 'tags')
        ->assertJsonPath('tags.0.name', 'added')
        ->assertJsonPath('tags.1.name', 'keep');

    assertDatabaseMissing('website_tags', ['website_id' => $website->id, 'name' => 'old']);
});

test('a partial website update keeps omitted fields and tags', function () {
    actingAsAdmin();

    $website = Website::factory()->create(['title' => 'Old', 'icon' => 'globe']);
    $website->tags()->create(['name' => 'keep']);

    patchJson("/api/v1/websites/{$website->id}", ['title' => null])
        ->assertOk()
        ->assertJsonPath('title', null)
        ->assertJsonPath('icon', 'globe')
        ->assertJsonPath('tags.0.name', 'keep');
});

test('an admin can delete a website with its tags', function () {
    actingAsAdmin();

    $website = Website::factory()->create();
    $website->tags()->create(['name' => 'tools']);

    deleteJson("/api/v1/websites/{$website->id}")->assertNoContent();

    assertDatabaseMissing('websites', ['id' => $website->id]);
    assertDatabaseMissing('website_tags', ['website_id' => $website->id]);
});

test('websites can be filtered by tag and sorted', function () {
    actingAsAdmin();

    $first = Website::factory()->create(['title' => 'B']);
    $first->tags()->create(['name' => 'tools']);
    Website::factory()->create(['title' => 'A']);

    getJson('/api/v1/websites?filter[tags.name]=tools')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->id);

    getJson('/api/v1/websites?sort=title')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'A');
});

test('store website validates input', function () {
    actingAsAdmin();

    Website::factory()->create(['url' => 'https://example.com']);

    postJson('/api/v1/websites', [])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    postJson('/api/v1/websites', ['url' => 'not a url'])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    postJson('/api/v1/websites', ['url' => 'https://example.com'])->assertUnprocessable()->assertJsonValidationErrors(['url']);
    postJson('/api/v1/websites', ['url' => 'https://new.example.com', 'tags' => [str_repeat('a', 101)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0']);
    postJson('/api/v1/websites', ['url' => 'https://new.example.com', 'tags' => ['tools', 'Tools']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0', 'tags.1']);
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
    patchJson("/api/v1/websites/{$website->id}", ['tags' => ['a', 'A']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0', 'tags.1']);

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
