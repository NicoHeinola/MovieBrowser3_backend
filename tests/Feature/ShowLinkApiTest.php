<?php

use App\Enums\ShowLinkType;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show link read endpoints require authentication', function () {
    $show = Show::factory()->create();

    getJson("/api/v1/shows/{$show->id}/links")->assertUnauthorized();
});

test('show link write endpoints require admin', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/links", [])
        ->assertForbidden();
});

test('authenticated users can list show links', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $source = Show::factory()->create();
    $targetA = Show::factory()->create();
    $targetB = Show::factory()->create();
    ShowLink::factory()->create(['source_show_id' => $source->id, 'target_show_id' => $targetA->id, 'type' => ShowLinkType::Sequel]);
    ShowLink::factory()->create(['source_show_id' => $source->id, 'target_show_id' => $targetB->id, 'type' => ShowLinkType::SpinOff]);

    getJson("/api/v1/shows/{$source->id}/links")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('authenticated users can view a single link', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $source = Show::factory()->create();
    $target = Show::factory()->create();
    $link = ShowLink::factory()->create([
        'source_show_id' => $source->id,
        'target_show_id' => $target->id,
        'type' => ShowLinkType::Sequel,
    ]);

    getJson("/api/v1/links/{$link->id}")
        ->assertOk()
        ->assertJsonPath('id', $link->id)
        ->assertJsonPath('source_show_id', $source->id)
        ->assertJsonPath('target_show_id', $target->id)
        ->assertJsonPath('type', 'sequel');
});

test('an admin can create a show link', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $target = Show::factory()->create();

    $response = postJson("/api/v1/shows/{$source->id}/links", [
        'target_show_id' => $target->id,
        'type' => 'sequel',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('source_show_id', $source->id)
        ->assertJsonPath('target_show_id', $target->id)
        ->assertJsonPath('type', 'sequel');

    assertDatabaseHas('show_links', [
        'source_show_id' => $source->id,
        'target_show_id' => $target->id,
        'type' => 'sequel',
    ]);
});

test('an admin can update a show link', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $target = Show::factory()->create();
    $newTarget = Show::factory()->create();
    $link = ShowLink::factory()->create([
        'source_show_id' => $source->id,
        'target_show_id' => $target->id,
        'type' => ShowLinkType::Sequel,
    ]);

    patchJson("/api/v1/links/{$link->id}", [
        'target_show_id' => $newTarget->id,
        'type' => 'prequel',
    ])
        ->assertOk()
        ->assertJsonPath('target_show_id', $newTarget->id)
        ->assertJsonPath('type', 'prequel');

    assertDatabaseHas('show_links', [
        'id' => $link->id,
        'target_show_id' => $newTarget->id,
        'type' => 'prequel',
    ]);
});

test('an admin can delete a show link', function () {
    actingAsAdmin();

    $link = ShowLink::factory()->create();

    deleteJson("/api/v1/links/{$link->id}")
        ->assertNoContent();

    assertDatabaseMissing('show_links', ['id' => $link->id]);
});

test('creating a show link validates type enum', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $target = Show::factory()->create();

    postJson("/api/v1/shows/{$source->id}/links", [
        'target_show_id' => $target->id,
        'type' => 'invalid_type',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('creating a show link requires target_show_id and type', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/links", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target_show_id', 'type']);
});

test('creating a show link validates target_show_id exists', function () {
    actingAsAdmin();

    $show = Show::factory()->create();

    postJson("/api/v1/shows/{$show->id}/links", [
        'target_show_id' => 99999,
        'type' => 'sequel',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['target_show_id']);
});

test('multiple link types per pair are allowed', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $target = Show::factory()->create();

    postJson("/api/v1/shows/{$source->id}/links", [
        'target_show_id' => $target->id,
        'type' => 'sequel',
    ])->assertCreated();

    postJson("/api/v1/shows/{$source->id}/links", [
        'target_show_id' => $target->id,
        'type' => 'suggested_next',
    ])->assertCreated();

    assertDatabaseCount('show_links', 2);
});

test('duplicate source-target-type link is rejected', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $target = Show::factory()->create();

    ShowLink::factory()->create([
        'source_show_id' => $source->id,
        'target_show_id' => $target->id,
        'type' => ShowLinkType::Sequel,
    ]);

    postJson("/api/v1/shows/{$source->id}/links", [
        'target_show_id' => $target->id,
        'type' => 'sequel',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('updating a show link to a duplicate source-target-type combination is rejected', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $firstTarget = Show::factory()->create();
    $secondTarget = Show::factory()->create();

    ShowLink::factory()->create([
        'source_show_id' => $source->id,
        'target_show_id' => $firstTarget->id,
        'type' => ShowLinkType::Sequel,
    ]);

    $linkToUpdate = ShowLink::factory()->create([
        'source_show_id' => $source->id,
        'target_show_id' => $secondTarget->id,
        'type' => ShowLinkType::Prequel,
    ]);

    patchJson("/api/v1/links/{$linkToUpdate->id}", [
        'target_show_id' => $firstTarget->id,
        'type' => 'sequel',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('deleting a show cascades to its links', function () {
    actingAsAdmin();

    $source = Show::factory()->create();
    $target = Show::factory()->create();
    ShowLink::factory()->create(['source_show_id' => $source->id, 'target_show_id' => $target->id]);

    assertDatabaseCount('show_links', 1);

    deleteJson("/api/v1/shows/{$source->id}")
        ->assertNoContent();

    assertDatabaseCount('show_links', 0);
});
