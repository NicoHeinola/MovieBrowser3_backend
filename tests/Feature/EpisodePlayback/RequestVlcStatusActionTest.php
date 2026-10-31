<?php

use App\Actions\EpisodePlayback\RequestVlcStatusAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use Illuminate\Support\Facades\Http;

test('it returns the VLC status', function () {
    Http::fake(['127.0.0.1:45678/requests/status.json*' => Http::response(['state' => 'playing'])]);

    expect(RequestVlcStatusAction::run(new VlcConnectionData(45678, 'secret')))->toBe(['state' => 'playing']);
});

test('it returns null when VLC does not answer', function () {
    Http::fake(['127.0.0.1:*' => Http::failedConnection()]);

    expect(RequestVlcStatusAction::run(new VlcConnectionData(45678, 'secret')))->toBeNull();
});

test('it returns null when VLC rejects the password', function () {
    Http::fake(['127.0.0.1:*' => Http::response('Unauthorized', 401)]);

    expect(RequestVlcStatusAction::run(new VlcConnectionData(45678, 'wrong')))->toBeNull();
});
