<?php

use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$sourcePath = dirname(__DIR__, 4).'/storage/app/testing-copy-source/video.mkv';

afterEach(fn () => File::deleteDirectory(dirname(dirname(__DIR__, 4).'/storage/app/testing-copy-source/video.mkv')));

test('it copies a video and keeps the original', function () use ($sourcePath) {
    putVideoFile($sourcePath, 'original');
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    $bytes = DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target));

    expect($bytes)->toBe(8)
        ->and(File::get($target))->toBe('original')
        ->and(File::get($sourcePath))->toBe('original')
        ->and(File::exists("{$target}.part"))->toBeFalse();
});

test('a rollback deletes the copy and its emptied folders but keeps the original', function () use ($sourcePath) {
    putVideoFile($sourcePath, 'original');
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    expect(fn () => DB::transaction(function () use ($sourcePath, $target): void {
        CopyVideoFileAction::run($sourcePath, $target);

        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom');

    expect(File::exists($target))->toBeFalse()
        ->and(File::isDirectory(videoBasePathForTests().'/1_Show'))->toBeFalse()
        ->and(File::get($sourcePath))->toBe('original');
});

test('it never overwrites or deletes a file already at the target, even on rollback', function () use ($sourcePath) {
    putVideoFile($sourcePath, 'original');
    $target = putVideoFile(videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv', 'already-here');

    expect(fn () => DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target)))
        ->toThrow(RuntimeException::class, 'Refusing to overwrite');

    expect(File::get($target))->toBe('already-here')
        ->and(File::get($sourcePath))->toBe('original');
});

test('copying a file onto itself is refused and leaves it intact', function () use ($sourcePath) {
    putVideoFile($sourcePath, 'original');

    expect(fn () => DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $sourcePath)))
        ->toThrow(RuntimeException::class, 'Refusing to overwrite');

    expect(File::get($sourcePath))->toBe('original');
});

test('it refuses to copy without enough free disk space', function () use ($sourcePath) {
    putVideoFile($sourcePath, 'original');
    ResolveFreeDiskSpaceAction::mock()->shouldReceive('handle')->andReturn(CopyVideoFileAction::RESERVED_DISK_BYTES);
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    expect(fn () => DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target)))
        ->toThrow(RuntimeException::class, 'Not enough free disk space');

    expect(File::exists($target))->toBeFalse()
        ->and(File::get($sourcePath))->toBe('original');
});
