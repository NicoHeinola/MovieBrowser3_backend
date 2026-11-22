<?php

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;

it('encodes an error without params as an empty object, not an empty list', function () {
    expect(json_encode(ApiErrorDetail::make(ApiErrorCode::Forbidden)))->toBe('{"code":"forbidden","params":{}}');
});

it('keeps a code given as text and its params', function () {
    expect(json_encode(ApiErrorDetail::make('video_relocation_failed', ['path' => 'a.mkv'])))
        ->toBe('{"code":"video_relocation_failed","params":{"path":"a.mkv"}}');
});

it('builds an error response with the status', function () {
    $response = ApiErrorDetail::response(ApiErrorCode::Forbidden, 403);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true))->toBe(['code' => 'forbidden', 'params' => []]);
});
