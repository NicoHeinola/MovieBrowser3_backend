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
