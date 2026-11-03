<?php

use App\Models\User\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withToken;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'releases.backend_repo_url' => 'https://github.com/owner/backend',
        'releases.frontend_repo_url' => 'git@github.com:owner/frontend.git',
    ]);
});

function fakeGithubReleases(string $backendTag, string $frontendTag): void
{
    Http::fake([
        'api.github.com/repos/owner/backend/releases/latest' => Http::response([
            'tag_name' => $backendTag,
            'html_url' => "https://github.com/owner/backend/releases/tag/{$backendTag}",
        ]),
        'api.github.com/repos/owner/frontend/releases/latest' => Http::response([
            'tag_name' => $frontendTag,
            'html_url' => "https://github.com/owner/frontend/releases/tag/{$frontendTag}",
        ]),
    ]);
}

function runningBackendVersion(): string
{
    return trim(file_get_contents(base_path('VERSION')));
}

test('the app version requires an admin', function () {
    getJson('/api/v1/app-version')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/app-version')->assertForbidden();
    Http::assertNothingSent();
});

test('an admin can read the running version and the latest releases', function () {
    Carbon::setTestNow('2026-11-03 10:00:00');
    fakeGithubReleases('v9.0.0', 'v8.1.0');
    actingAsAdmin();

    getJson('/api/v1/app-version')
        ->assertOk()
        ->assertExactJson([
            'backend' => [
                'current' => runningBackendVersion(),
                'latest' => '9.0.0',
                'release_url' => 'https://github.com/owner/backend/releases/tag/v9.0.0',
            ],
            'frontend' => [
                'latest' => '8.1.0',
                'release_url' => 'https://github.com/owner/frontend/releases/tag/v8.1.0',
            ],
            'checked_at' => '2026-11-03T10:00:00.000000Z',
        ]);
});

test('github answers are cached between requests', function () {
    fakeGithubReleases('v9.0.0', 'v8.1.0');
    actingAsAdmin();

    getJson('/api/v1/app-version')->assertOk();
    getJson('/api/v1/app-version')->assertOk()->assertJsonPath('backend.latest', '9.0.0');

    Http::assertSentCount(2);

    // Past the cache time GitHub is asked again
    travel(config('releases.cache_seconds') + 1)->seconds();
    getJson('/api/v1/app-version')->assertOk();

    Http::assertSentCount(4);
});

test('a failed github check returns no latest release, and is retried sooner', function () {
    // Earlier Http::fake stubs win over later ones, so one fake switches from failing to answering
    $online = false;
    Http::fake([
        // By reference: an arrow function would freeze $online at false
        'api.github.com/repos/owner/backend/*' => function () use (&$online) {
            return $online
                ? Http::response(['tag_name' => 'v9.0.0', 'html_url' => 'https://github.com/owner/backend/releases/tag/v9.0.0'])
                : Http::response(['message' => 'rate limited'], 403);
        },
        'api.github.com/repos/owner/frontend/*' => fn () => throw new ConnectionException('offline'),
    ]);
    actingAsAdmin();

    getJson('/api/v1/app-version')
        ->assertOk()
        ->assertJsonPath('backend.current', runningBackendVersion())
        ->assertJsonPath('backend.latest', null)
        ->assertJsonPath('backend.release_url', null)
        ->assertJsonPath('frontend.latest', null)
        ->assertJsonPath('frontend.release_url', null);

    // The failure is cached too, so an offline install doesn't call GitHub on every request
    getJson('/api/v1/app-version')->assertOk();
    Http::assertSentCount(1);

    travel(config('releases.failure_cache_seconds') + 1)->seconds();
    $online = true;
    getJson('/api/v1/app-version')->assertOk()->assertJsonPath('backend.latest', '9.0.0');
});

test('a repo that is not on GitHub is not checked', function () {
    config(['releases.backend_repo_url' => 'https://gitlab.com/owner/backend']);
    fakeGithubReleases('v9.0.0', 'v8.1.0');
    actingAsAdmin();

    getJson('/api/v1/app-version')
        ->assertOk()
        ->assertJsonPath('backend.latest', null)
        ->assertJsonPath('frontend.latest', '8.1.0');

    Http::assertSentCount(1);
});

test('a release tag that is not vX.Y.Z counts as no release', function () {
    fakeGithubReleases('nightly', 'v1.2');
    actingAsAdmin();

    getJson('/api/v1/app-version')
        ->assertOk()
        ->assertJsonPath('backend.latest', null)
        ->assertJsonPath('frontend.latest', null);
});
