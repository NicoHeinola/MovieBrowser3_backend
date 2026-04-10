<?php

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

test('show endpoints require authentication', function () {
    getJson('/api/v1/shows')->assertUnauthorized();
    postJson('/api/v1/shows', [])->assertUnauthorized();
    deleteJson('/api/v1/shows', ['ids' => [1]])->assertUnauthorized();
});

test('show endpoints forbid authenticated non-admin users', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    withToken($token);

    getJson('/api/v1/shows')
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');

    deleteJson('/api/v1/shows', ['ids' => [1]])
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('an admin can create a show with nested titles', function () {
    actingAsAdmin();

    $response = postJson('/api/v1/shows', [
        'banner_url' => ' https://cdn.example.com/banners/severance.jpg ',
        'card_image_url' => 'https://cdn.example.com/cards/severance.jpg',
        'preview_url' => '',
        'titles' => [
            [
                'title' => ' Severance ',
                'is_primary' => true,
            ],
            [
                'title' => 'Severance: Director Cut',
                'is_primary' => false,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('banner_url', 'https://cdn.example.com/banners/severance.jpg')
        ->assertJsonPath('preview_url', null)
        ->assertJsonPath('titles.0.title', 'Severance')
        ->assertJsonPath('titles.0.is_primary', true)
        ->assertJsonCount(2, 'titles');

    assertDatabaseHas('shows', [
        'banner_url' => 'https://cdn.example.com/banners/severance.jpg',
        'card_image_url' => 'https://cdn.example.com/cards/severance.jpg',
        'preview_url' => null,
    ]);

    assertDatabaseHas('show_titles', [
        'title' => 'Severance',
        'is_primary' => true,
    ]);

    assertDatabaseHas('show_titles', [
        'title' => 'Severance: Director Cut',
        'is_primary' => false,
    ]);
});

test('an admin can list and fetch shows with titles', function () {
    actingAsAdmin();

    $show = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/the-bear.jpg',
    ]);

    ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'The Bear',
    ]);
    ShowTitle::factory()->for($show)->create([
        'title' => 'The Bear International',
    ]);

    getJson('/api/v1/shows')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.banner_url', 'https://cdn.example.com/banners/the-bear.jpg')
        ->assertJsonPath('0.titles.0.title', 'The Bear')
        ->assertJsonCount(2, '0.titles');

    getJson("/api/v1/shows/{$show->id}")
        ->assertOk()
        ->assertJsonPath('id', $show->id)
        ->assertJsonPath('titles.0.title', 'The Bear')
        ->assertJsonCount(2, 'titles');
});

test('an admin can filter shows by related title through query builder', function () {
    actingAsAdmin();

    $matchingShow = Show::factory()->create();
    $otherShow = Show::factory()->create();

    ShowTitle::factory()->for($matchingShow)->primary()->create([
        'title' => 'The Bear',
    ]);
    ShowTitle::factory()->for($otherShow)->primary()->create([
        'title' => 'Andor',
    ]);

    getJson('/api/v1/shows?filter[title]=bear')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $matchingShow->id)
        ->assertJsonPath('0.titles.0.title', 'The Bear');
});

test('an admin can sort shows through query builder', function () {
    actingAsAdmin();

    $laterShow = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/zeta.jpg',
    ]);
    $earlierShow = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/alpha.jpg',
    ]);

    ShowTitle::factory()->for($laterShow)->primary()->create([
        'title' => 'Zeta Show',
    ]);
    ShowTitle::factory()->for($earlierShow)->primary()->create([
        'title' => 'Alpha Show',
    ]);

    getJson('/api/v1/shows?sort=banner_url')
        ->assertOk()
        ->assertJsonPath('0.id', $earlierShow->id)
        ->assertJsonPath('1.id', $laterShow->id);

    getJson('/api/v1/shows?sort=-banner_url')
        ->assertOk()
        ->assertJsonPath('0.id', $laterShow->id)
        ->assertJsonPath('1.id', $earlierShow->id);
});

test('an admin can update a show and replace its titles', function () {
    actingAsAdmin();

    $show = Show::factory()->create([
        'preview_url' => 'https://cdn.example.com/previews/original.mp4',
    ]);

    ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Original Title',
    ]);
    ShowTitle::factory()->for($show)->create([
        'title' => 'Original Alt Title',
    ]);

    $response = patchJson("/api/v1/shows/{$show->id}", [
        'banner_url' => 'https://cdn.example.com/banners/updated.jpg',
        'preview_url' => '',
        'titles' => [
            [
                'title' => 'Updated Primary Title',
                'is_primary' => true,
            ],
            [
                'title' => 'Updated Secondary Title',
                'is_primary' => false,
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('banner_url', 'https://cdn.example.com/banners/updated.jpg')
        ->assertJsonPath('preview_url', null)
        ->assertJsonPath('titles.0.title', 'Updated Primary Title')
        ->assertJsonCount(2, 'titles');

    assertDatabaseHas('shows', [
        'id' => $show->id,
        'banner_url' => 'https://cdn.example.com/banners/updated.jpg',
        'preview_url' => null,
    ]);

    assertDatabaseMissing('show_titles', [
        'show_id' => $show->id,
        'title' => 'Original Title',
    ]);

    assertDatabaseHas('show_titles', [
        'show_id' => $show->id,
        'title' => 'Updated Primary Title',
        'is_primary' => true,
    ]);
});

test('an admin can delete a show and its titles cascade', function () {
    actingAsAdmin();

    $show = Show::factory()->create();
    $title = ShowTitle::factory()->for($show)->primary()->create();

    deleteJson("/api/v1/shows/{$show->id}")
        ->assertNoContent();

    assertDatabaseMissing('shows', [
        'id' => $show->id,
    ]);

    assertDatabaseMissing('show_titles', [
        'id' => $title->id,
    ]);
});

test('an admin can delete multiple shows at once', function () {
    actingAsAdmin();

    $firstShow = Show::factory()->create();
    $secondShow = Show::factory()->create();
    $remainingShow = Show::factory()->create();

    $firstTitle = ShowTitle::factory()->for($firstShow)->primary()->create();
    $secondTitle = ShowTitle::factory()->for($secondShow)->primary()->create();
    $remainingTitle = ShowTitle::factory()->for($remainingShow)->primary()->create();

    deleteJson('/api/v1/shows', [
        'ids' => [$firstShow->id, $secondShow->id],
    ])
        ->assertOk()
        ->assertJsonPath('deleted_count', 2);

    assertDatabaseMissing('shows', [
        'id' => $firstShow->id,
    ]);

    assertDatabaseMissing('shows', [
        'id' => $secondShow->id,
    ]);

    assertDatabaseHas('shows', [
        'id' => $remainingShow->id,
    ]);

    assertDatabaseMissing('show_titles', [
        'id' => $firstTitle->id,
    ]);

    assertDatabaseMissing('show_titles', [
        'id' => $secondTitle->id,
    ]);

    assertDatabaseHas('show_titles', [
        'id' => $remainingTitle->id,
    ]);
});

test('bulk show deletion validates the ids payload', function () {
    actingAsAdmin();

    deleteJson('/api/v1/shows', [
        'ids' => ['abc', 999999],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0', 'ids.1']);
});

test('show creation requires exactly one primary title', function () {
    actingAsAdmin();

    $response = postJson('/api/v1/shows', [
        'banner_url' => 'https://cdn.example.com/banners/andor.jpg',
        'card_image_url' => 'https://cdn.example.com/cards/andor.jpg',
        'preview_url' => 'https://cdn.example.com/previews/andor.mp4',
        'titles' => [
            [
                'title' => 'Andor',
                'is_primary' => false,
            ],
            [
                'title' => 'Andor Alt',
                'is_primary' => false,
            ],
        ],
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['titles']);

    assertDatabaseCount('shows', 0);
});

test('show update can change only scalar fields without replacing titles', function () {
    actingAsAdmin();

    $show = Show::factory()->create([
        'banner_url' => 'https://cdn.example.com/banners/original.jpg',
    ]);

    ShowTitle::factory()->for($show)->primary()->create([
        'title' => 'Still Here',
    ]);

    patchJson("/api/v1/shows/{$show->id}", [
        'card_image_url' => 'https://cdn.example.com/cards/new-card.jpg',
    ])
        ->assertOk()
        ->assertJsonPath('card_image_url', 'https://cdn.example.com/cards/new-card.jpg')
        ->assertJsonCount(1, 'titles');

    assertDatabaseHas('show_titles', [
        'show_id' => $show->id,
        'title' => 'Still Here',
        'is_primary' => true,
    ]);
});
