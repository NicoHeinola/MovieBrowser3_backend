<?php

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    $this->show = Show::factory()->create();
    $this->url = "/api/v1/shows/{$this->show->id}/video-file-check";
});

function episodeOfShow(Show $show, array $attributes = []): Episode
{
    return Episode::factory()->for(ShowEntry::factory()->for($show), 'entry')->create($attributes);
}

test('checking a show requires authentication', function () {
    postJson($this->url)->assertUnauthorized();
});

test('checking a show requires an admin', function () {
    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    postJson($this->url)->assertForbidden();
});

test('a show that does not exist is not found', function () {
    actingAsAdmin();

    postJson('/api/v1/shows/999/video-file-check')->assertNotFound();
});

test('checking a show records its files, says what is wrong and leaves other shows alone', function () {
    actingAsAdmin();
    $found = episodeOfShow($this->show, ['has_video_file' => false]);
    putVideoFile(ResolveEpisodeVideoPathAction::run($found), matroskaVideo(1440));
    $unreadable = episodeOfShow($this->show);
    putVideoFile(ResolveEpisodeVideoPathAction::run($unreadable), 'not a video');
    $gone = episodeOfShow($this->show, ['video_duration_seconds' => 1200]);
    episodeOfShow($this->show, ['filename' => '', 'has_video_file' => false]);
    $elsewhere = Episode::factory()->create();

    postJson($this->url)->assertOk()->assertExactJson([
        'missing_video_files_count' => 1,
        'unknown_video_durations_count' => 1,
    ]);

    expect($found->fresh()->only('has_video_file', 'video_duration_seconds'))->toBe(['has_video_file' => true, 'video_duration_seconds' => 1440])
        ->and($gone->fresh()->only('has_video_file', 'video_duration_seconds'))->toBe(['has_video_file' => false, 'video_duration_seconds' => 1200])
        ->and($elsewhere->fresh()->has_video_file)->toBeTrue()
        // Not a check of everything: that one's last result stays
        ->and(VideoFileCheck::query()->exists())->toBeFalse();
});

test('checking a show without its drive says so and changes nothing', function (bool $isEmptyFolder) {
    actingAsAdmin();
    $episode = episodeOfShow($this->show, ['video_duration_seconds' => 1200]);

    if ($isEmptyFolder) {
        File::ensureDirectoryExists(videoBaseFolderForTests());
    }

    postJson($this->url)->assertConflict()->assertExactJson(['code' => 'video_base_folder_unavailable', 'params' => []]);

    expect($episode->fresh()->only('has_video_file', 'video_duration_seconds'))->toBe(['has_video_file' => true, 'video_duration_seconds' => 1200]);
})->with(['no folder' => false, 'an empty folder, as an unmounted drive shows' => true]);

test('a show can not be checked while video files are being moved', function () {
    actingAsAdmin();
    VideoBaseFolderMove::factory()->create();

    postJson($this->url)->assertConflict()->assertJsonPath('code', 'video_base_folder_move_running');
});
