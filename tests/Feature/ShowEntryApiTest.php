<?php

use App\Enums\ShowEntryType;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show entry read endpoints require authentication', function () {
    $show = Show::factory()->create();

    getJson("/api/v1/shows/{$show->id}/entries")->assertUnauthorized();
});

test('show entry write endpoints require admin', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/entries", [])
        ->assertForbidden();
});

test('authenticated users can list show entries', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    ShowEntry::factory()->for($show)->create(['name' => 'Season 1', 'type' => ShowEntryType::Season, 'sort_order' => 1]);
    ShowEntry::factory()->for($show)->tvSpecial()->create(['sort_order' => 2]);

    getJson("/api/v1/shows/{$show->id}/entries")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('authenticated users can view a single entry', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create([
        'name' => 'Season 1: Stone Wars',
        'type' => ShowEntryType::Season,
    ]);

    getJson("/api/v1/entries/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('name', 'Season 1: Stone Wars')
        ->assertJsonPath('type', 'season');
});

test('an admin can create a show entry', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    $response = postJson("/api/v1/shows/{$show->id}/entries", [
        'type' => 'season',
        'name' => 'Season 1',
        'sort_order' => 1,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('name', 'Season 1')
        ->assertJsonPath('type', 'season')
        ->assertJsonPath('show_id', $show->id)
        ->assertJsonPath('sort_order', 1);

    assertDatabaseHas('show_entries', [
        'show_id' => $show->id,
        'type' => 'season',
        'name' => 'Season 1',
        'sort_order' => 1,
    ]);
});

test('an admin can update a show entry', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create([
        'name' => 'Season 1',
        'type' => ShowEntryType::Season,
    ]);

    patchJson("/api/v1/entries/{$entry->id}", [
        'name' => 'Season 1: Stone Wars',
        'sort_order' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Season 1: Stone Wars')
        ->assertJsonPath('type', 'season')
        ->assertJsonPath('sort_order', 5);

    assertDatabaseHas('show_entries', [
        'id' => $entry->id,
        'name' => 'Season 1: Stone Wars',
        'sort_order' => 5,
    ]);
});

test('an admin can delete a show entry', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create();

    deleteJson("/api/v1/entries/{$entry->id}")
        ->assertNoContent();

    assertDatabaseMissing('show_entries', ['id' => $entry->id]);
});

test('deleting a show entry cascades to its episodes', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $entry = ShowEntry::factory()->for($show)->create();
    Episode::factory()->for($entry, 'entry')->create(['sequence_number' => 1]);
    Episode::factory()->for($entry, 'entry')->create(['sequence_number' => 2]);

    assertDatabaseCount('episodes', 2);

    deleteJson("/api/v1/entries/{$entry->id}")
        ->assertNoContent();

    assertDatabaseCount('episodes', 0);
});

test('creating a show entry validates type enum', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/entries", [
        'type' => 'invalid_type',
        'name' => 'Season 1',
        'sort_order' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('creating a show entry requires name, type, and sort_order', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/entries", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'name', 'sort_order']);
});
