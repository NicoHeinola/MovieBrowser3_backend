<?php

use App\Models\Category\Category;
use App\Models\Show\Show;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('category endpoints require authentication', function () {
    $show = Show::factory()->create();
    $category = Category::factory()->create();

    getJson('/api/v1/categories')->assertUnauthorized();
    getJson("/api/v1/categories/{$category->id}")->assertUnauthorized();

    postJson('/api/v1/categories', [
        'name' => 'Drama',
        'value' => 'drama',
    ])->assertUnauthorized();

    postJson("/api/v1/shows/{$show->id}/categories", [
        'category_id' => $category->id,
    ])->assertUnauthorized();

    deleteJson("/api/v1/shows/{$show->id}/categories/{$category->id}")->assertUnauthorized();
});

test('category write endpoints require admin', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $show = Show::factory()->create();
    $category = Category::factory()->create();

    postJson('/api/v1/categories', [
        'name' => 'Drama',
        'value' => 'drama',
    ])->assertForbidden();

    postJson("/api/v1/shows/{$show->id}/categories", [
        'category_id' => $category->id,
    ])->assertForbidden();

    deleteJson("/api/v1/shows/{$show->id}/categories/{$category->id}")->assertForbidden();
});

test('authenticated users can list categories', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $firstCategory = Category::factory()->create([
        'name' => 'Drama',
        'value' => 'drama',
    ]);
    Category::factory()->create([
        'name' => 'Comedy',
        'value' => 'comedy',
    ]);

    getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $firstCategory->id)
        ->assertJsonPath('data.0.name', 'Drama');
});

test('authenticated users can view a category', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    withToken($token);

    $category = Category::factory()->create([
        'name' => 'Science Fiction',
        'value' => 'science-fiction',
        'icon' => 'planet',
    ]);

    getJson("/api/v1/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('id', $category->id)
        ->assertJsonPath('name', 'Science Fiction')
        ->assertJsonPath('value', 'science-fiction')
        ->assertJsonPath('icon', 'planet');
});

test('an admin can create a category', function () {
    actingAsAdmin();

    postJson('/api/v1/categories', [
        'name' => 'Drama',
        'value' => 'drama',
        'icon' => 'clapperboard',
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'Drama')
        ->assertJsonPath('value', 'drama')
        ->assertJsonPath('icon', 'clapperboard');

    assertDatabaseHas('categories', [
        'name' => 'Drama',
        'value' => 'drama',
        'icon' => 'clapperboard',
    ]);
});

test('an admin can update a category', function () {
    actingAsAdmin();

    $category = Category::factory()->create([
        'name' => 'Drama',
        'value' => 'drama',
        'icon' => 'mask',
    ]);

    patchJson("/api/v1/categories/{$category->id}", [
        'name' => 'Comedy',
        'value' => 'comedy',
        'icon' => 'laugh',
    ])
        ->assertOk()
        ->assertJsonPath('id', $category->id)
        ->assertJsonPath('name', 'Comedy')
        ->assertJsonPath('value', 'comedy')
        ->assertJsonPath('icon', 'laugh');

    assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Comedy',
        'value' => 'comedy',
        'icon' => 'laugh',
    ]);
});

test('an admin can delete a category', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $category = Category::factory()->create();

    postJson("/api/v1/shows/{$show->id}/categories", [
        'category_id' => $category->id,
    ])->assertCreated();

    deleteJson("/api/v1/shows/{$show->id}/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();

    assertDatabaseMissing('category_show', [
        'show_id' => $show->id,
        'category_id' => $category->id,
    ]);
});

test('an admin can attach a category to a show', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $category = Category::factory()->create([
        'name' => 'Science Fiction',
        'value' => 'science-fiction',
    ]);

    postJson("/api/v1/shows/{$show->id}/categories", [
        'category_id' => $category->id,
    ])
        ->assertCreated()
        ->assertJsonPath('id', $category->id)
        ->assertJsonPath('name', 'Science Fiction')
        ->assertJsonPath('value', 'science-fiction');

    assertDatabaseHas('category_show', [
        'show_id' => $show->id,
        'category_id' => $category->id,
    ]);

    getJson("/api/v1/shows/{$show->id}")
        ->assertOk()
        ->assertJsonCount(1, 'categories')
        ->assertJsonPath('categories.0.name', 'Science Fiction');

    deleteJson("/api/v1/shows/{$show->id}/categories/{$category->id}")
        ->assertNoContent();

    assertDatabaseMissing('category_show', [
        'show_id' => $show->id,
        'category_id' => $category->id,
    ]);
});

test('store category validates required fields', function () {
    actingAsAdmin();

    postJson('/api/v1/categories', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'value']);
});
