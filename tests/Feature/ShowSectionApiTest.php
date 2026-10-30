<?php

use App\Models\Category\Category;
use App\Models\ShowSection\ShowSection;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show section endpoints require authentication', function () {
    $section = ShowSection::factory()->create();

    getJson('/api/v1/show-sections')->assertUnauthorized();
    getJson("/api/v1/show-sections/{$section->id}")->assertUnauthorized();

    postJson('/api/v1/show-sections', [
        'category_id' => $section->category_id,
        'description' => 'Top picks',
        'sort_order' => 0,
    ])->assertUnauthorized();

    patchJson("/api/v1/show-sections/{$section->id}", [
        'description' => 'Updated',
    ])->assertUnauthorized();

    deleteJson("/api/v1/show-sections/{$section->id}")->assertUnauthorized();
});

test('authenticated non-admin users can read show sections but cannot write', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $section = ShowSection::factory()->create([
        'sort_order' => 3,
    ]);

    getJson('/api/v1/show-sections')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $section->id);

    getJson("/api/v1/show-sections/{$section->id}")
        ->assertOk()
        ->assertJsonPath('id', $section->id);

    postJson('/api/v1/show-sections', [
        'category_id' => Category::factory()->create()->id,
        'description' => 'Top picks',
        'sort_order' => 1,
    ])->assertForbidden();

    patchJson("/api/v1/show-sections/{$section->id}", [
        'description' => 'Updated',
    ])->assertForbidden();

    deleteJson("/api/v1/show-sections/{$section->id}")->assertForbidden();
});

test('show sections are listed in ascending sort order', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $lastSection = ShowSection::factory()->create(['sort_order' => 20]);
    $firstSection = ShowSection::factory()->create(['sort_order' => 2]);
    $middleSection = ShowSection::factory()->create(['sort_order' => 10]);

    getJson('/api/v1/show-sections')
        ->assertOk()
        ->assertJsonPath('data.0.id', $firstSection->id)
        ->assertJsonPath('data.1.id', $middleSection->id)
        ->assertJsonPath('data.2.id', $lastSection->id);
});

test('an admin can create a show section', function () {
    actingAsAdmin();

    $category = Category::factory()->create([
        'name' => 'Drama',
        'value' => 'drama',
    ]);

    postJson('/api/v1/show-sections', [
        'category_id' => $category->id,
        'description' => 'Best drama picks right now.',
        'sort_order' => 4,
    ])
        ->assertCreated()
        ->assertJsonPath('category_id', $category->id)
        ->assertJsonPath('description', 'Best drama picks right now.')
        ->assertJsonPath('sort_order', 4)
        ->assertJsonPath('category.id', $category->id)
        ->assertJsonPath('category.name', 'Drama');

    assertDatabaseHas('show_sections', [
        'category_id' => $category->id,
        'description' => 'Best drama picks right now.',
        'sort_order' => 4,
    ]);
});

test('an admin can update a show section including sort order', function () {
    actingAsAdmin();

    $section = ShowSection::factory()->create([
        'description' => 'Original description',
        'sort_order' => 12,
    ]);

    patchJson("/api/v1/show-sections/{$section->id}", [
        'description' => 'Updated description',
        'sort_order' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('id', $section->id)
        ->assertJsonPath('description', 'Updated description')
        ->assertJsonPath('sort_order', 1);

    assertDatabaseHas('show_sections', [
        'id' => $section->id,
        'description' => 'Updated description',
        'sort_order' => 1,
    ]);
});

test('an admin can delete a show section', function () {
    actingAsAdmin();

    $section = ShowSection::factory()->create();

    deleteJson("/api/v1/show-sections/{$section->id}")
        ->assertNoContent();

    assertDatabaseMissing('show_sections', [
        'id' => $section->id,
    ]);
});

test('store show section validates required fields and category uniqueness', function () {
    actingAsAdmin();

    $category = Category::factory()->create();
    ShowSection::factory()->create([
        'category_id' => $category->id,
    ]);

    postJson('/api/v1/show-sections', [
        'category_id' => $category->id,
        'sort_order' => -1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'sort_order']);

    postJson('/api/v1/show-sections', [
        'description' => 'Missing required fields',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'sort_order']);
});
