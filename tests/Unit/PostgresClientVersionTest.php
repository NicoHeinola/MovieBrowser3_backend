<?php

// pg_dump refuses a server newer than itself, so the image's client must follow the postgres image
test('the image installs the same postgres client major as the database runs', function () {
    $root = dirname(__DIR__, 2);

    preg_match('/postgresql-client-(\d+)/', file_get_contents("{$root}/Dockerfile"), $client);
    preg_match('/image: postgres:(\d+)/', file_get_contents("{$root}/deploy/compose.yml"), $server);

    expect($client[1] ?? null)->not->toBeNull()->toBe($server[1] ?? null);
});
