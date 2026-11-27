<?php

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Services\Vlc\VlcService;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

test('it returns what VLC is doing', function () {
    Saloon::fake(['127.0.0.1:45678/requests/status.json*' => MockResponse::make([
        'state' => 'playing',
        'time' => 754,
        'length' => 1440,
        'information' => ['category' => ['meta' => ['filename' => '7_Episode.mkv'], 'Stream 0' => ['Type' => 'Video']]],
    ])]);

    $status = (new VlcService(new VlcConnectionData(45678, 'secret')))->getStatus();

    expect($status->toArray())->toBe(['state' => 'playing', 'filename' => '7_Episode.mkv', 'positionSeconds' => 754, 'durationSeconds' => 1440]);
});

test('a VLC without a file has no filename and no length', function () {
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped', 'time' => 0, 'length' => 0])]);

    $status = (new VlcService(new VlcConnectionData(45678, 'secret')))->getStatus();

    expect($status)->filename->toBeNull()->durationSeconds->toBeNull()->isPlaying()->toBeFalse();
});

test('it returns null when VLC does not answer', function () {
    Saloon::fake(['127.0.0.1:*' => saloonConnectionRefused()]);

    expect((new VlcService(new VlcConnectionData(45678, 'secret')))->getStatus())->toBeNull();
});

test('it returns null when VLC rejects the password', function () {
    Saloon::fake(['127.0.0.1:*' => MockResponse::make('Unauthorized', 401)]);

    expect((new VlcService(new VlcConnectionData(45678, 'wrong')))->getStatus())->toBeNull();
});

test('a path reaches VLC with its spaces as %20, since VLC reads + as a plus sign', function () {
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);

    (new VlcService(new VlcConnectionData(45678, 'secret')))->enqueueFile('G:\Spirited Away\A+B & é.mkv');

    // The URL that goes out, not the query as given: only building it shows how the spaces are written
    expect((string) saloonSent()->sole()['request']->createPsrRequest()->getUri())
        ->toBe('http://127.0.0.1:45678/requests/status.json?command=in_enqueue&input=G%3A%5CSpirited%20Away%5CA%2BB%20%26%20%C3%A9.mkv');
});
