<?php

use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Services\Vlc\VlcService;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

test('it returns the VLC status', function () {
    Saloon::fake(['127.0.0.1:45678/requests/status.json*' => MockResponse::make(['state' => 'playing'])]);

    expect(app(VlcService::class)->getStatus(new VlcConnectionData(45678, 'secret')))->toBe(['state' => 'playing']);
});

test('it returns null when VLC does not answer', function () {
    Saloon::fake(['127.0.0.1:*' => saloonConnectionRefused()]);

    expect(app(VlcService::class)->getStatus(new VlcConnectionData(45678, 'secret')))->toBeNull();
});

test('it returns null when VLC rejects the password', function () {
    Saloon::fake(['127.0.0.1:*' => MockResponse::make('Unauthorized', 401)]);

    expect(app(VlcService::class)->getStatus(new VlcConnectionData(45678, 'wrong')))->toBeNull();
});

test('a path reaches VLC with its spaces as %20, since VLC reads + as a plus sign', function () {
    Saloon::fake(['127.0.0.1:*' => MockResponse::make(['state' => 'stopped'])]);

    app(VlcService::class)->getStatus(new VlcConnectionData(45678, 'secret'), ['command' => 'in_play', 'input' => 'G:\Spirited Away\A+B & é.mkv']);

    // The URL that goes out, not the query as given: only building it shows how the spaces are written
    expect((string) saloonSent()->sole()['request']->createPsrRequest()->getUri())
        ->toBe('http://127.0.0.1:45678/requests/status.json?command=in_play&input=G%3A%5CSpirited%20Away%5CA%2BB%20%26%20%C3%A9.mkv');
});
