<?php

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show title read endpoints require authentication', function () {
    $show = Show::factory()->create();

    getJson("/api/v1/shows/{$show->id}/titles")->assertUnauthorized();
});

test('show title write endpoints require admin', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/titles", [])
        ->assertForbidden();
});

test('authenticated users can list show titles', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    ShowTitle::factory()->for($show)->primary()->create(['title' => 'Main Title']);
    ShowTitle::factory()->for($show)->create(['title' => 'Alt Title']);

    getJson("/api/v1/shows/{$show->id}/titles")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('authenticated users can view a single title', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    $title = ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Severance',
    ]);

    getJson("/api/v1/titles/{$title->id}")
        ->assertOk()
        ->assertJsonPath('id', $title->id)
        ->assertJsonPath('title', 'Severance')
        ->assertJsonPath('is_primary', true);
});

test('an admin can create a title for a show', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    $response = postJson("/api/v1/shows/{$show->id}/titles", [
        'title' => 'Severance',
        'is_primary' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('title', 'Severance')
        ->assertJsonPath('is_primary', true)
        ->assertJsonPath('show_id', $show->id);

    assertDatabaseHas('show_titles', [
        'show_id' => $show->id,
        'title' => 'Severance',
        'is_primary' => true,
    ]);
});

test('an admin can update a title', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $title = ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Original',
    ]);

    $response = patchJson("/api/v1/titles/{$title->id}", [
        'title' => 'Updated',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('title', 'Updated')
        ->assertJsonPath('is_primary', true);

    assertDatabaseHas('show_titles', [
        'id' => $title->id,
        'title' => 'Updated',
        'is_primary' => true,
    ]);
});

test('an admin can delete a non-primary title', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    ShowTitle::factory()->for($show)->primary()->create();
    $altTitle = ShowTitle::factory()->for($show)->create(['title' => 'Alt']);

    deleteJson("/api/v1/titles/{$altTitle->id}")
        ->assertNoContent();

    assertDatabaseMissing('show_titles', [
        'id' => $altTitle->id,
    ]);
});

test('creating a primary title auto-demotes the existing primary', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $existingPrimary = ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Old Primary',
    ]);

    postJson("/api/v1/shows/{$show->id}/titles", [
        'title' => 'New Primary',
        'is_primary' => true,
    ])->assertCreated();

    assertDatabaseHas('show_titles', [
        'id' => $existingPrimary->id,
        'is_primary' => false,
    ]);

    assertDatabaseHas('show_titles', [
        'show_id' => $show->id,
        'title' => 'New Primary',
        'is_primary' => true,
    ]);
});

test('updating a title to primary auto-demotes the existing primary', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $existingPrimary = ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Old Primary',
    ]);
    $secondary = ShowTitle::factory()->for($show)->create([
        'title' => 'Secondary',
    ]);

    patchJson("/api/v1/titles/{$secondary->id}", [
        'is_primary' => true,
    ])->assertOk();

    assertDatabaseHas('show_titles', [
        'id' => $existingPrimary->id,
        'is_primary' => false,
    ]);

    assertDatabaseHas('show_titles', [
        'id' => $secondary->id,
        'is_primary' => true,
    ]);
});

test('cannot delete the only primary title', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $primary = ShowTitle::factory()->for($show)->primary()->create();

    deleteJson("/api/v1/titles/{$primary->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    assertDatabaseHas('show_titles', [
        'id' => $primary->id,
    ]);
});

test('store title validates required fields', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/titles", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'is_primary']);
});

test('update title validates field types', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $title = ShowTitle::factory()->for($show)->primary()->create();

    patchJson("/api/v1/titles/{$title->id}", [
        'title' => '',
        'is_primary' => 'not-a-boolean',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'is_primary']);
});
