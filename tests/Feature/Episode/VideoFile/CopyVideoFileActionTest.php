<?php

use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

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

/**
 * Fakes wslpath as the identity and robocopy as a plain copy of `{dir} {dir} {file}`, exiting with `$exitCode`.
 */
function fakeRobocopy(int $exitCode = 1): void
{
    Process::fake(function (PendingProcess $process) use ($exitCode) {
        if ($process->command[0] === 'wslpath') {
            return Process::result(output: $process->command[2]."\n");
        }

        [, , , $fromDirectory, $toDirectory, $file] = $process->command;

        if ($exitCode < 8) {
            File::copy("{$fromDirectory}/{$file}", "{$toDirectory}/{$file}");
        }

        return Process::result(exitCode: $exitCode);
    });
}

test('with windows it copies through robocopy and names the copy only once it is complete', function () use ($sourcePath) {
    fakeRobocopy();
    putVideoFile($sourcePath, 'original');
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    $bytes = DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target, true));

    expect($bytes)->toBe(8)
        ->and(File::get($target))->toBe('original')
        ->and(File::get($sourcePath))->toBe('original')
        ->and(File::exists(dirname($target).'/video.mkv'))->toBeFalse();

    Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, 0, 6) === [
        'setsid', '-w', 'robocopy.exe', dirname($sourcePath), dirname($target), 'video.mkv',
    ] && in_array('/R:0', $process->command, true));
});

test('with windows a failed robocopy fails the copy and the rollback leaves only the original', function () use ($sourcePath) {
    fakeRobocopy(exitCode: 8);
    putVideoFile($sourcePath, 'original');
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    expect(fn () => DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target, true)))
        ->toThrow(RuntimeException::class, 'Robocopy could not copy');

    expect(File::isDirectory(videoBasePathForTests().'/1_Show'))->toBeFalse()
        ->and(File::get($sourcePath))->toBe('original');
});

test('with windows a robocopy that copied nothing fails the copy', function () use ($sourcePath) {
    fakeRobocopy(exitCode: 0);
    putVideoFile($sourcePath, 'original');
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';

    expect(fn () => DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target, true)))
        ->toThrow(RuntimeException::class, 'exit code 0');

    expect(File::exists($target))->toBeFalse();
});

test('with windows a file with the original\'s name already in the target folder is never touched', function () use ($sourcePath) {
    fakeRobocopy();
    putVideoFile($sourcePath, 'original');
    $target = videoBasePathForTests().'/1_Show/1_Season/1_Episode.mkv';
    $inTheWay = putVideoFile(dirname($target).'/video.mkv', 'already-here');

    expect(fn () => DB::transaction(fn (): int => CopyVideoFileAction::run($sourcePath, $target, true)))
        ->toThrow(RuntimeException::class, 'Refusing to overwrite');

    expect(File::get($inTheWay))->toBe('already-here');
    Process::assertNothingRan();
});
