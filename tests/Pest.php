<?php

use App\Models\Setting\Setting;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // The seeded video_base_path is the real storage/app/videos; keep every test away from it.
    ->beforeEach(fn () => useTestVideoBasePath())
    ->afterEach(fn () => File::deleteDirectory(videoBasePathForTests()))
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

function actingAsAdmin(): User
{
    $user = User::factory()->admin()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    \Pest\Laravel\withToken($token);

    return $user;
}

function videoBasePathForTests(): string
{
    return dirname(__DIR__).'/storage/app/testing-video-base';
}

$GLOBALS['videoBasePath'] = videoBasePathForTests();

function useTestVideoBasePath(): string
{
    Setting::query()->whereKey('video_base_path')->update(['value' => videoBasePathForTests()]);

    return videoBasePathForTests();
}

function putVideoFile(string $path, string $contents = 'video-bytes'): string
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
