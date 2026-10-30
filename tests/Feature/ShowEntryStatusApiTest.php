<?php

use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryStatus\ShowEntryStatus;
use App\Models\User\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\withToken;

function actingAsRegularUser(): User
{
    $user = User::factory()->create();
    withToken($user->createToken('test-token')->plainTextToken);

    return $user;
}

test('show entry status endpoints require authentication', function () {
    $entry = ShowEntry::factory()->create();

    putJson("/api/v1/show-entries/{$entry->id}/status", ['status' => 'watching'])->assertUnauthorized();
    deleteJson("/api/v1/show-entries/{$entry->id}/status")->assertUnauthorized();
});

test('a regular user can set a show entry status', function () {
    $user = actingAsRegularUser();
    $entry = ShowEntry::factory()->create();

    putJson("/api/v1/show-entries/{$entry->id}/status", ['status' => 'on_hold'])
        ->assertOk()
        ->assertExactJson(['show_entry_id' => $entry->id, 'status' => 'on_hold']);

    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'on_hold']);
});

test('setting a status again replaces the previous one', function () {
    $user = actingAsRegularUser();
    $entry = ShowEntry::factory()->create();
    ShowEntryStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'watching']);

    putJson("/api/v1/show-entries/{$entry->id}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    assertDatabaseCount('show_entry_statuses', 1);
    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'completed']);
});

test('entries of one show can have different statuses', function () {
    $user = actingAsRegularUser();
    $show = Show::factory()->create();
    $season1 = ShowEntry::factory()->create(['show_id' => $show->id]);
    $season2 = ShowEntry::factory()->create(['show_id' => $show->id]);

    putJson("/api/v1/show-entries/{$season1->id}/status", ['status' => 'completed'])->assertOk();
    putJson("/api/v1/show-entries/{$season2->id}/status", ['status' => 'watching'])->assertOk();

    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $season1->id, 'user_id' => $user->id, 'status' => 'completed']);
    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $season2->id, 'user_id' => $user->id, 'status' => 'watching']);
});

test('statuses are tracked per user', function () {
    $other = User::factory()->create();
    $entry = ShowEntry::factory()->create();
    ShowEntryStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $other->id, 'status' => 'dropped']);

    $user = actingAsRegularUser();
    putJson("/api/v1/show-entries/{$entry->id}/status", ['status' => 'watching'])->assertOk();

    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $entry->id, 'user_id' => $other->id, 'status' => 'dropped']);
    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'watching']);
});

test('set status validates the status value', function () {
    actingAsRegularUser();
    $entry = ShowEntry::factory()->create();

    putJson("/api/v1/show-entries/{$entry->id}/status", [])->assertUnprocessable()->assertJsonValidationErrors('status');
    putJson("/api/v1/show-entries/{$entry->id}/status", ['status' => 'binging'])->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('setting a status on a missing entry returns 404', function () {
    actingAsRegularUser();

    putJson('/api/v1/show-entries/999/status', ['status' => 'watching'])->assertNotFound();
});

test('a user can delete their own status only', function () {
    $other = User::factory()->create();
    $entry = ShowEntry::factory()->create();
    ShowEntryStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $other->id]);

    $user = actingAsRegularUser();
    ShowEntryStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $user->id]);

    deleteJson("/api/v1/show-entries/{$entry->id}/status")->assertNoContent();

    assertDatabaseMissing('show_entry_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id]);
    assertDatabaseHas('show_entry_statuses', ['show_entry_id' => $entry->id, 'user_id' => $other->id]);
});

test('entry payloads include only the current users status', function () {
    $other = User::factory()->create();
    $show = Show::factory()->create();
    $season1 = ShowEntry::factory()->create(['show_id' => $show->id]);
    $season2 = ShowEntry::factory()->create(['show_id' => $show->id]);
    ShowEntryStatus::factory()->create(['show_entry_id' => $season1->id, 'user_id' => $other->id, 'status' => 'dropped']);

    $admin = actingAsAdmin();
    getJson("/api/v1/entries/{$season1->id}")->assertOk()->assertJsonPath('status', null);

    ShowEntryStatus::factory()->create(['show_entry_id' => $season1->id, 'user_id' => $admin->id, 'status' => 'completed']);
    ShowEntryStatus::factory()->create(['show_entry_id' => $season2->id, 'user_id' => $admin->id, 'status' => 'watching']);

    getJson("/api/v1/entries/{$season1->id}")
        ->assertOk()
        ->assertJsonPath('status', ['show_entry_id' => $season1->id, 'status' => 'completed']);

    getJson("/api/v1/shows/{$show->id}/entries")
        ->assertOk()
        ->assertJsonPath('data.0.status.status', 'completed')
        ->assertJsonPath('data.1.status.status', 'watching');

    getJson("/api/v1/shows/{$show->id}")
        ->assertOk()
        ->assertJsonPath('entries.0.status.status', 'completed')
        ->assertJsonPath('entries.1.status.status', 'watching');
});

test('entries can be filtered by the current users status', function () {
    $admin = actingAsAdmin();
    $show = Show::factory()->create();
    $watching = ShowEntry::factory()->create(['show_id' => $show->id]);
    $completed = ShowEntry::factory()->create(['show_id' => $show->id]);
    ShowEntry::factory()->create(['show_id' => $show->id]);
    ShowEntryStatus::factory()->create(['show_entry_id' => $watching->id, 'user_id' => $admin->id, 'status' => 'watching']);
    ShowEntryStatus::factory()->create(['show_entry_id' => $completed->id, 'user_id' => $admin->id, 'status' => 'completed']);
    ShowEntryStatus::factory()->create(['show_entry_id' => $completed->id, 'user_id' => User::factory(), 'status' => 'watching']);

    getJson("/api/v1/shows/{$show->id}/entries?filter[status]=watching")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $watching->id);
});
