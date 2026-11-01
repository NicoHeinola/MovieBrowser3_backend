<?php

use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\Website\Website;
use Illuminate\Support\Carbon;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

test('dashboard stats require an admin', function () {
    getJson('/api/v1/dashboard-stats')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/dashboard-stats')->assertForbidden();
});

test('an admin can read the dashboard stats', function () {
    Carbon::setTestNow('2026-11-01 12:00:00');
    actingAsAdmin();

    $show = Show::factory()->create(['created_at' => now()->subDays(2)]);
    Show::factory()->create(['created_at' => now()->subDays(7)]);
    Show::factory()->create(['created_at' => now()->subDays(20)]);
    Show::factory()->create(['created_at' => now()->subDays(31)]);
    Episode::factory()->count(3)->for(ShowEntry::factory()->for($show), 'entry')->create();
    Website::factory()->count(2)->create();

    getJson('/api/v1/dashboard-stats')
        ->assertOk()
        ->assertExactJson([
            'shows_count' => 4,
            'shows_added_last_7_days' => 2,
            'shows_added_last_30_days' => 3,
            'episodes_count' => 3,
            'websites_count' => 2,
            'users_count' => User::query()->count(),
        ]);
});
