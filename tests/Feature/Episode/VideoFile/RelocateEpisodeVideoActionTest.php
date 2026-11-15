<?php

use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\PruneEmptyDirectoriesAction;
use App\Actions\Episode\VideoFile\RelocateEpisodeVideoAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Exceptions\VideoFileMoveException;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->from = videoBasePathForTests().'/from';
    $this->to = videoBasePathForTests().'/to';
    $entry = ShowEntry::factory()->create(['name' => 'Season 1']);
    $this->episode = Episode::factory()->for($entry, 'entry')->create(['name' => 'Episode 1', 'filename' => 'ep.mkv']);
    $relative = "{$entry->show_id}_{$entry->show_id}/{$entry->id}_Season 1/{$this->episode->id}_Episode 1.mkv";
    $this->fromPath = "{$this->from}/{$relative}";
    $this->toPath = "{$this->to}/{$relative}";
});

/**
 * The action as if the two base folders were on different disks, so it copies instead of renaming.
 */
function relocateBetweenDisks(): RelocateEpisodeVideoAction
{
    return new class(app(CopyVideoFileAction::class), app(PruneEmptyDirectoriesAction::class), app(ResolveEpisodeVideoPathAction::class)) extends RelocateEpisodeVideoAction
    {
        protected function onSameDisk(string $fromPath, string $toPath): bool
        {
            return false;
        }
    };
}

test('between disks it copies the video, then deletes the original and its emptied folders', function () {
    putVideoFile($this->fromPath, 'bytes');

    relocateBetweenDisks()->handle($this->episode, $this->from, $this->to);

    expect(File::get($this->toPath))->toBe('bytes')
        ->and(File::exists("{$this->toPath}.part"))->toBeFalse()
        ->and(File::exists($this->fromPath))->toBeFalse()
        ->and(File::isDirectory(dirname($this->fromPath, 2)))->toBeFalse()
        ->and(File::isDirectory($this->from))->toBeTrue();
});

test('a part file left by a dead copy is replaced', function () {
    putVideoFile($this->fromPath, 'bytes');
    putVideoFile("{$this->toPath}.part", 'by');

    relocateBetweenDisks()->handle($this->episode, $this->from, $this->to);

    expect(File::get($this->toPath))->toBe('bytes')
        ->and(File::exists("{$this->toPath}.part"))->toBeFalse();
});

test('moving back removes the part file a killed copy left and its emptied folders', function () {
    putVideoFile($this->fromPath, 'bytes');
    putVideoFile("{$this->toPath}.part", 'by');

    // Back from `to` to `from`: the killed copy never finished, so the original is still in place
    relocateBetweenDisks()->handle($this->episode, $this->to, $this->from);

    expect(File::get($this->fromPath))->toBe('bytes')
        ->and(File::exists("{$this->toPath}.part"))->toBeFalse()
        ->and(File::isDirectory(dirname($this->toPath, 2)))->toBeFalse();
});

test('a copy that finished before its original was deleted only deletes the original', function () {
    putVideoFile($this->fromPath, 'bytes');
    putVideoFile($this->toPath, 'bytes');

    app(RelocateEpisodeVideoAction::class)->handle($this->episode, $this->from, $this->to);

    expect(File::get($this->toPath))->toBe('bytes')
        ->and(File::exists($this->fromPath))->toBeFalse();
});

test('it never overwrites a different file at the target', function () {
    putVideoFile($this->fromPath, 'bytes');
    putVideoFile($this->toPath, 'someone else');

    expect(fn () => app(RelocateEpisodeVideoAction::class)->handle($this->episode, $this->from, $this->to))
        ->toThrow(VideoFileMoveException::class, 'Refusing to overwrite');

    expect(File::get($this->fromPath))->toBe('bytes')
        ->and(File::get($this->toPath))->toBe('someone else');
});

test('a missing or already moved video is left alone', function () {
    app(RelocateEpisodeVideoAction::class)->handle($this->episode, $this->from, $this->to);
    expect(File::exists($this->toPath))->toBeFalse();

    putVideoFile($this->toPath, 'bytes');
    app(RelocateEpisodeVideoAction::class)->handle($this->episode, $this->from, $this->to);
    expect(File::get($this->toPath))->toBe('bytes');
});

test('an original that cannot be deleted after its copy fails the move and keeps both', function () {
    putVideoFile($this->fromPath, 'bytes');
    // Read-only folder: the copy can read the file but nothing can delete it
    chmod(dirname($this->fromPath), 0555);

    try {
        expect(fn () => relocateBetweenDisks()->handle($this->episode, $this->from, $this->to))
            ->toThrow(fn (VideoFileMoveException $exception) => expect($exception->path)->toBe($this->fromPath));
    } finally {
        chmod(dirname($this->fromPath), 0755);
    }

    expect(File::get($this->fromPath))->toBe('bytes')
        ->and(File::get($this->toPath))->toBe('bytes');

    // Running again finishes the move: the copy already matches
    relocateBetweenDisks()->handle($this->episode, $this->from, $this->to);

    expect(File::exists($this->fromPath))->toBeFalse();
});
