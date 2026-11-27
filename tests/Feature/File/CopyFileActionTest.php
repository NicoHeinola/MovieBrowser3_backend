<?php

use App\Actions\File\CopyFileAction;
use App\Actions\File\CreateFileLinkAction;
use App\Actions\File\ResolveDiskSpaceAction;
use App\Dtos\File\DiskSpaceData;
use App\Exceptions\NotEnoughDiskSpaceException;
use App\Services\HostBridge\Requests\CopyFileRequest;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    Saloon::fake([]);
    $this->source = putVideoFile(videoBaseFolderForTests().'/source/video.mkv', 'original');
    $this->target = videoBaseFolderForTests().'/1_Show/1_Season/1_Episode.mkv';
});

/**
 * As if the source and the target were on different drives, so the file can't be linked.
 */
function onDifferentDrives(): void
{
    CreateFileLinkAction::mock()->shouldReceive('handle')->andReturn(false);
}

test('on one drive it links, which takes no space and leaves both names working on their own', function () {
    ResolveDiskSpaceAction::mock()->shouldReceive('handle')->andReturn(new DiskSpaceData(freeBytes: 0, totalBytes: 1024));

    CopyFileAction::run($this->source, $this->target);

    expect(fileinode($this->target))->toBe(fileinode($this->source));

    File::delete($this->source);

    expect(File::get($this->target))->toBe('original');
});

test('between drives it copies and keeps the original', function () {
    onDifferentDrives();

    CopyFileAction::run($this->source, $this->target);

    expect(File::get($this->target))->toBe('original')
        ->and(File::get($this->source))->toBe('original')
        ->and(fileinode($this->target))->not->toBe(fileinode($this->source))
        ->and(scandir(dirname($this->target)))->toBe(['.', '..', '1_Episode.mkv']);
});

test('in docker the bridge copies between drives', function () {
    onDifferentDrives();
    config(['host_bridge.enabled' => true]);
    Saloon::fake([CopyFileRequest::class => function (): MockResponse {
        File::copy($this->source, "{$this->target}.part");

        return MockResponse::make('', 204);
    }]);

    CopyFileAction::run($this->source, $this->target);

    expect(File::get($this->target))->toBe('original')
        ->and(saloonSent(CopyFileRequest::class)->sole()['fields'])->toBe(['from' => $this->source, 'to' => "{$this->target}.part"]);
});

test('a bridge that can not copy leaves the copy to the app, and its half copy goes', function (MockResponse|Closure $answer) {
    onDifferentDrives();
    config(['host_bridge.enabled' => true]);
    Saloon::fake([CopyFileRequest::class => $answer]);
    putVideoFile("{$this->target}.part.copying/video.mkv", 'or');

    CopyFileAction::run($this->source, $this->target);

    expect(File::get($this->target))->toBe('original')
        ->and(scandir(dirname($this->target)))->toBe(['.', '..', '1_Episode.mkv']);
})->with([
    'not running' => fn () => saloonConnectionRefused(),
    'too old' => fn () => MockResponse::make(['message' => 'Not found.'], 404),
    'a failed copy' => fn () => MockResponse::make(['message' => 'Robocopy failed'], 422),
]);

test('a copy that was killed halfway starts over', function () {
    onDifferentDrives();
    putVideoFile("{$this->target}.part", 'orig');

    CopyFileAction::run($this->source, $this->target);

    expect(File::get($this->target))->toBe('original')
        ->and(File::exists("{$this->target}.part"))->toBeFalse();
});

test('a failed copy leaves no half of it', function () {
    onDifferentDrives();
    ResolveDiskSpaceAction::mock()->shouldReceive('handle')->andReturn(new DiskSpaceData(freeBytes: 1024, totalBytes: 1024));

    expect(fn () => CopyFileAction::run($this->source, $this->target))->toThrow(NotEnoughDiskSpaceException::class);

    expect(scandir(dirname($this->target)))->toBe(['.', '..'])
        ->and(File::get($this->source))->toBe('original');
});

test('it never overwrites or deletes a file already at the target', function () {
    putVideoFile($this->target, 'already-here');

    expect(fn () => CopyFileAction::run($this->source, $this->target))->toThrow(RuntimeException::class, 'Refusing to overwrite');

    expect(File::get($this->target))->toBe('already-here')
        ->and(File::get($this->source))->toBe('original');
});

test('copying a file onto itself is refused and leaves it intact', function () {
    expect(fn () => CopyFileAction::run($this->source, $this->source))->toThrow(RuntimeException::class, 'Refusing to overwrite');

    expect(File::get($this->source))->toBe('original');
});
