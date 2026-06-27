<?php

use App\Actions\Episode\VideoFile\PruneEmptyDirectoriesAction;
use Illuminate\Support\Facades\File;

$root = null;

beforeEach(function () use (&$root) {
    $root = dirname(__DIR__, 4).'/storage/app/prune-empty-directories/'.uniqid('', true);
    File::ensureDirectoryExists($root);
});

afterEach(function () use (&$root) {
    File::deleteDirectory($root);
});

it('removes empty directories until the stop path', function () use (&$root) {
    $startDirectory = $root.'/show/entry/episode';
    $stopDirectory = $root.'/show';

    File::ensureDirectoryExists($startDirectory);

    app(PruneEmptyDirectoriesAction::class)->handle($startDirectory, $stopDirectory);

    expect(File::exists($startDirectory))->toBeFalse();
    expect(File::exists($root.'/show/entry'))->toBeFalse();
    expect(File::exists($stopDirectory))->toBeTrue();
});

it('stops pruning when it reaches a non-empty parent directory', function () use (&$root) {
    $startDirectory = $root.'/show/entry/episode';
    $nonEmptyDirectory = $root.'/show/entry';
    $stopDirectory = $root.'/show';

    File::ensureDirectoryExists($startDirectory);
    File::put($nonEmptyDirectory.'/keep.txt', 'keep');

    app(PruneEmptyDirectoriesAction::class)->handle($startDirectory, $stopDirectory);

    expect(File::exists($startDirectory))->toBeFalse();
    expect(File::exists($nonEmptyDirectory))->toBeTrue();
    expect(File::exists($nonEmptyDirectory.'/keep.txt'))->toBeTrue();
    expect(File::exists($stopDirectory))->toBeTrue();
});
