<?php

use Spatie\LaravelData\Mappers\SnakeCaseMapper;

return [
    'structure_caching' => [
        'directories' => [app_path('Dtos')],
        'cache' => [
            'store' => env('CACHE_STORE', env('CACHE_DRIVER', 'file')),
            'prefix' => 'laravel-data',
            'duration' => null,
        ],
        'reflection_discovery' => [
            'enabled' => true,
            'base_path' => base_path(),
            'root_namespace' => null,
        ],
    ],
    'name_mapping_strategy' => [
        'input' => SnakeCaseMapper::class,
        'output' => null,
    ],
];
