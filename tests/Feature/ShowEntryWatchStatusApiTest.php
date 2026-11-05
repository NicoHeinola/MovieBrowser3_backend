<?php

use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
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

/**
 * Creates a show with one entry per given status, marked for the user; null leaves that entry unmarked.
 *
 * @param  array<int, string|null>  $statuses
 */
function showWithStatuses(User $user, array $statuses): Show
{
    $show = Show::factory()->create();

    foreach ($statuses as $status) {
        $entry = ShowEntry::factory()->create(['show_id' => $show->id]);

        if ($status !== null) {
            ShowEntryWatchStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => $status]);
        }
    }

    return $show;
}

test('show entry status endpoints require authentication', function () {
    $entry = ShowEntry::factory()->create();

    putJson("/api/v1/show-entries/{$entry->id}/watch-status", ['status' => 'watching'])->assertUnauthorized();
    deleteJson("/api/v1/show-entries/{$entry->id}/watch-status")->assertUnauthorized();
});

test('a regular user can set a show entry status', function () {
    $user = actingAsRegularUser();
    $entry = ShowEntry::factory()->create();

    putJson("/api/v1/show-entries/{$entry->id}/watch-status", ['status' => 'on_hold'])
        ->assertOk()
        ->assertExactJson(['show_entry_id' => $entry->id, 'status' => 'on_hold']);

    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'on_hold']);
});

test('setting a status again replaces the previous one', function () {
    $user = actingAsRegularUser();
    $entry = ShowEntry::factory()->create();
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'watching']);

    putJson("/api/v1/show-entries/{$entry->id}/watch-status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    assertDatabaseCount('show_entry_watch_statuses', 1);
    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'completed']);
});

test('entries of one show can have different statuses', function () {
    $user = actingAsRegularUser();
    $show = Show::factory()->create();
    $season1 = ShowEntry::factory()->create(['show_id' => $show->id]);
    $season2 = ShowEntry::factory()->create(['show_id' => $show->id]);

    putJson("/api/v1/show-entries/{$season1->id}/watch-status", ['status' => 'completed'])->assertOk();
    putJson("/api/v1/show-entries/{$season2->id}/watch-status", ['status' => 'watching'])->assertOk();

    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $season1->id, 'user_id' => $user->id, 'status' => 'completed']);
    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $season2->id, 'user_id' => $user->id, 'status' => 'watching']);
});

test('statuses are tracked per user', function () {
    $other = User::factory()->create();
    $entry = ShowEntry::factory()->create();
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $other->id, 'status' => 'dropped']);

    $user = actingAsRegularUser();
    putJson("/api/v1/show-entries/{$entry->id}/watch-status", ['status' => 'watching'])->assertOk();

    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $entry->id, 'user_id' => $other->id, 'status' => 'dropped']);
    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id, 'status' => 'watching']);
});

test('set status validates the status value', function () {
    actingAsRegularUser();
    $entry = ShowEntry::factory()->create();

    putJson("/api/v1/show-entries/{$entry->id}/watch-status", [])->assertUnprocessable()->assertJsonValidationErrors('status');
    putJson("/api/v1/show-entries/{$entry->id}/watch-status", ['status' => 'binging'])->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('setting a status on a missing entry returns 404', function () {
    actingAsRegularUser();

    putJson('/api/v1/show-entries/999/watch-status', ['status' => 'watching'])->assertNotFound();
});

test('a user can delete their own status only', function () {
    $other = User::factory()->create();
    $entry = ShowEntry::factory()->create();
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $other->id]);

    $user = actingAsRegularUser();
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $entry->id, 'user_id' => $user->id]);

    deleteJson("/api/v1/show-entries/{$entry->id}/watch-status")->assertNoContent();

    assertDatabaseMissing('show_entry_watch_statuses', ['show_entry_id' => $entry->id, 'user_id' => $user->id]);
    assertDatabaseHas('show_entry_watch_statuses', ['show_entry_id' => $entry->id, 'user_id' => $other->id]);
});

test('entry payloads include only the current users status', function () {
    $other = User::factory()->create();
    $show = Show::factory()->create();
    $season1 = ShowEntry::factory()->create(['show_id' => $show->id, 'sort_order' => 1]);
    $season2 = ShowEntry::factory()->create(['show_id' => $show->id, 'sort_order' => 2]);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $season1->id, 'user_id' => $other->id, 'status' => 'dropped']);

    $admin = actingAsAdmin();
    getJson("/api/v1/entries/{$season1->id}")->assertOk()->assertJsonPath('watch_status', null);

    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $season1->id, 'user_id' => $admin->id, 'status' => 'completed']);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $season2->id, 'user_id' => $admin->id, 'status' => 'watching']);

    getJson("/api/v1/entries/{$season1->id}")
        ->assertOk()
        ->assertJsonPath('watch_status', ['show_entry_id' => $season1->id, 'status' => 'completed']);

    getJson("/api/v1/shows/{$show->id}/entries")
        ->assertOk()
        ->assertJsonPath('data.0.watch_status.status', 'completed')
        ->assertJsonPath('data.1.watch_status.status', 'watching');

    getJson("/api/v1/shows/{$show->id}")
        ->assertOk()
        ->assertJsonPath('entries.0.watch_status.status', 'completed')
        ->assertJsonPath('entries.1.watch_status.status', 'watching');
});

test('entries can be filtered by the current users status', function () {
    $admin = actingAsAdmin();
    $show = Show::factory()->create();
    $watching = ShowEntry::factory()->create(['show_id' => $show->id]);
    $completed = ShowEntry::factory()->create(['show_id' => $show->id]);
    ShowEntry::factory()->create(['show_id' => $show->id]);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $watching->id, 'user_id' => $admin->id, 'status' => 'watching']);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $completed->id, 'user_id' => $admin->id, 'status' => 'completed']);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $completed->id, 'user_id' => User::factory(), 'status' => 'watching']);

    getJson("/api/v1/shows/{$show->id}/entries?filter[watch_status]=watching")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $watching->id);
});

test('shows can be filtered by the current users combined status', function () {
    $user = actingAsRegularUser();
    $watching = showWithStatuses($user, ['watching', 'watching']);
    $completed = showWithStatuses($user, ['completed', 'completed']);
    $differing = showWithStatuses($user, ['watching', 'completed']);
    $partly = showWithStatuses($user, ['completed', null]);
    $onHold = showWithStatuses($user, ['on_hold']);
    showWithStatuses($user, [null]);
    Show::factory()->create();

    getJson('/api/v1/shows?filter[watch_status]=watching&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$watching->id]);

    getJson('/api/v1/shows?filter[watch_status]=completed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$completed->id]);

    getJson('/api/v1/shows?filter[watch_status]=mixed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id, $partly->id]);

    getJson('/api/v1/shows?filter[watch_status][]=completed&filter[watch_status][]=on_hold&filter[watch_status][]=mixed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$completed->id, $differing->id, $partly->id, $onHold->id]);

    getJson('/api/v1/shows?filter[watch_status]=dropped')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('shows can exclude the current users combined status', function () {
    $user = actingAsRegularUser();
    $watching = showWithStatuses($user, ['watching', 'watching']);
    $completed = showWithStatuses($user, ['completed', 'completed']);
    $differing = showWithStatuses($user, ['watching', 'completed']);
    $partly = showWithStatuses($user, ['completed', null]);
    $unmarked = showWithStatuses($user, [null]);
    $noEntries = Show::factory()->create();

    getJson('/api/v1/shows?filter[exclude_watch_status]=completed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$watching->id, $differing->id, $partly->id, $unmarked->id, $noEntries->id]);

    getJson('/api/v1/shows?filter[exclude_watch_status]=mixed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$watching->id, $completed->id, $unmarked->id, $noEntries->id]);

    getJson('/api/v1/shows?filter[exclude_watch_status][]=completed&filter[exclude_watch_status][]=mixed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$watching->id, $unmarked->id, $noEntries->id]);

    getJson('/api/v1/shows?filter[watch_status]=watching&filter[exclude_watch_status]=completed')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$watching->id]);
});

test('other users statuses never count toward the combined status', function () {
    $user = actingAsRegularUser();
    $other = User::factory()->create();

    // Only another user marked it, so the current user has no combined status.
    $unmarked = showWithStatuses($other, ['completed']);

    // The other user's rows must neither fill the gap on the second entry nor differ from the first.
    $partly = showWithStatuses($user, ['completed', null]);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $partly->entries()->orderByDesc('id')->value('id'), 'user_id' => $other->id, 'status' => 'completed']);
    $completed = showWithStatuses($user, ['completed']);
    ShowEntryWatchStatus::factory()->create(['show_entry_id' => $completed->entries()->value('id'), 'user_id' => $other->id, 'status' => 'dropped']);

    getJson('/api/v1/shows?filter[watch_status]=completed')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$completed->id]);

    getJson('/api/v1/shows?filter[watch_status]=mixed')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$partly->id]);

    getJson('/api/v1/shows?filter[exclude_watch_status]=completed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$unmarked->id, $partly->id]);
});

test('unknown show status filter values match nothing and exclude nothing', function () {
    $user = actingAsRegularUser();
    $completed = showWithStatuses($user, ['completed']);
    $differing = showWithStatuses($user, ['watching', 'completed']);

    getJson('/api/v1/shows?filter[watch_status]=binging')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    getJson('/api/v1/shows?filter[watch_status][]=binging&filter[watch_status][]=completed')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$completed->id]);

    getJson('/api/v1/shows?filter[exclude_watch_status]=binging&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$completed->id, $differing->id]);
});

test('shows can be filtered by any entry having the current users status', function () {
    $user = actingAsRegularUser();
    $differing = showWithStatuses($user, ['watching', 'completed']);
    $completed = showWithStatuses($user, ['completed', 'completed']);
    showWithStatuses(User::factory()->create(), ['completed']);
    showWithStatuses($user, [null]);
    Show::factory()->create();

    getJson('/api/v1/shows?filter[entry_watch_status]=watching')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id]);

    getJson('/api/v1/shows?filter[entry_watch_status]=completed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id, $completed->id]);

    getJson('/api/v1/shows?filter[entry_watch_status][]=watching&filter[entry_watch_status][]=dropped')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id]);

    getJson('/api/v1/shows?filter[entry_watch_status]=dropped')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('shows can exclude any entry having the current users status', function () {
    $user = actingAsRegularUser();
    $differing = showWithStatuses($user, ['watching', 'completed']);
    $dropped = showWithStatuses($user, ['dropped']);
    $otherUsers = showWithStatuses(User::factory()->create(), ['completed']);
    $unmarked = showWithStatuses($user, [null]);
    $noEntries = Show::factory()->create();

    getJson('/api/v1/shows?filter[exclude_entry_watch_status]=completed&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$dropped->id, $otherUsers->id, $unmarked->id, $noEntries->id]);

    getJson('/api/v1/shows?filter[exclude_entry_watch_status]=dropped&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id, $otherUsers->id, $unmarked->id, $noEntries->id]);

    getJson('/api/v1/shows?filter[exclude_entry_watch_status][]=completed&filter[exclude_entry_watch_status][]=dropped&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$otherUsers->id, $unmarked->id, $noEntries->id]);
});

test('mixed and unknown values are not any-entry statuses', function (string $value) {
    $user = actingAsRegularUser();
    $differing = showWithStatuses($user, ['watching', 'completed']);
    $completed = showWithStatuses($user, ['completed']);

    getJson("/api/v1/shows?filter[entry_watch_status]={$value}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    getJson("/api/v1/shows?filter[exclude_entry_watch_status]={$value}&sort=id")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id, $completed->id]);
})->with(['mixed', 'binging']);

test('any-entry filters combine with the combined status filters', function () {
    $user = actingAsRegularUser();
    $differing = showWithStatuses($user, ['completed', 'watching']);
    $watching = showWithStatuses($user, ['watching']);
    showWithStatuses($user, ['completed', 'completed']);

    getJson('/api/v1/shows?filter[exclude_watch_status]=completed&filter[entry_watch_status]=watching&sort=id')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id, $watching->id]);

    getJson('/api/v1/shows?filter[watch_status]=mixed&filter[entry_watch_status]=watching')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$differing->id]);
});
