<?php

use App\Actions\Episode\VideoFile\ReadVideoFileDurationAction;
use Illuminate\Support\Facades\File;

$root = null;

beforeEach(function () use (&$root) {
    $root = dirname(__DIR__, 4).'/storage/app/read-video-file-duration/'.uniqid('', true);
    File::ensureDirectoryExists($root);
});

afterEach(function () use (&$root) {
    File::deleteDirectory($root);
});

/**
 * A silent 8 kHz, 8-bit mono WAV file.
 */
function silentWav(int $seconds): string
{
    $data = str_repeat("\x80", 8000 * $seconds);

    return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8)
        .'data'.pack('V', strlen($data)).$data;
}

it('reads a Matroska file length, rounded to seconds', function () use (&$root) {
    File::put("{$root}/episode.mkv", matroskaVideo(1440.6));

    expect(ReadVideoFileDurationAction::run("{$root}/episode.mkv"))->toBe(1441);
});

it('reads other formats too', function () use (&$root) {
    File::put("{$root}/episode.wav", silentWav(3));

    expect(ReadVideoFileDurationAction::run("{$root}/episode.wav"))->toBe(3);
});

it('returns null for a Matroska file without a length, a file that is not a video, and a missing file', function () use (&$root) {
    File::put("{$root}/no-length.mkv", matroskaVideo(null));
    File::put("{$root}/text.mkv", 'not a video at all');

    expect(ReadVideoFileDurationAction::run("{$root}/no-length.mkv"))->toBeNull()
        ->and(ReadVideoFileDurationAction::run("{$root}/text.mkv"))->toBeNull()
        ->and(ReadVideoFileDurationAction::run("{$root}/missing.mkv"))->toBeNull()
        ->and(ReadVideoFileDurationAction::run($root))->toBeNull();
});

it('returns null for a Matroska file cut off inside its header', function () use (&$root) {
    File::put("{$root}/cut.mkv", substr(matroskaVideo(1440.0), 0, 40));

    expect(ReadVideoFileDurationAction::run("{$root}/cut.mkv"))->toBeNull();
});
