<?php

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Finder\Finder;

test('folders are listed without SPL iterators, which skip entries on a Windows drive under WSL', function () {
    $iterators = ['DirectoryIterator', 'FilesystemIterator', 'GlobIterator', 'RecursiveDirectoryIterator'];

    // arch()'s toBeUsed() skips PHP's built-in classes, so read the class names from the code tokens instead
    $offenders = collect(Finder::create()->files()->in(app_path())->name('*.php'))
        ->filter(fn (SplFileInfo $file): bool => collect(PhpToken::tokenize($file->getContents()))
            ->filter(fn (PhpToken $token): bool => $token->is([T_STRING, T_NAME_FULLY_QUALIFIED]))
            ->contains(fn (PhpToken $token): bool => in_array(ltrim($token->text, '\\'), $iterators, true)))
        ->map(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

arch('resources never touch the disk')
    ->expect('App\Http\Resources')
    ->not->toUse([
        'Illuminate\Support\Facades\File',
        'Illuminate\Support\Facades\Storage',
        'Illuminate\Filesystem\Filesystem',
        'file_exists',
        'filesize',
        'is_dir',
        'is_file',
    ]);

arch('actions never get request objects')
    ->expect('App\Actions')
    ->not->toUse(['App\Http', Request::class, FormRequest::class]);

arch('models hold no application logic')
    ->expect('App\Models')
    ->not->toUse('App\Actions');

arch('actions are *Action classes using AsAction')
    ->expect('App\Actions')
    ->classes()
    ->toHaveSuffix('Action')
    ->toUseTrait(AsAction::class)
    // Shared traits for a domain's actions live in its Concerns folder and are not actions themselves. classes() still
    // includes traits, so ignore every such folder. arch() reads this while the file loads, before the app (and so
    // app_path()) exists.
    ->ignoring(collect(Finder::create()->directories()->in(dirname(__DIR__, 2).'/app/Actions')->name('Concerns'))
        ->map(fn (SplFileInfo $folder): string => 'App\\Actions\\'.str_replace('/', '\\', $folder->getRelativePathname()))
        ->values()
        ->all());

arch('services only talk to their outside system')
    ->expect('App\\Services')
    ->not->toUse(['App\\Actions', 'App\\Models', 'App\\Http']);

arch('outside systems are reached through a service\'s Saloon connector')
    ->expect('Saloon')
    ->toOnlyBeUsedIn('App\\Services');

arch('nothing calls an outside system with the plain HTTP client')
    ->expect(['Illuminate\\Support\\Facades\\Http', 'Illuminate\\Http\\Client'])
    ->not->toBeUsed();

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

test('"already running" exceptions are not reported', function () {
    $classes = collect(Finder::create()->files()->in(app_path())->name('*RunningException.php'))
        ->map(fn (SplFileInfo $file): string => 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->values()
        ->all();

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(is_subclass_of($class, ShouldntReport::class))->toBeTrue("{$class} must implement ShouldntReport");
    }
});

test('every env() key of the project has a line in .env.example', function () {
    $envKeys = function (array $paths): array {
        return collect(Finder::create()->files()->in($paths)->name('*.php'))
            ->flatMap(function (SplFileInfo $file): array {
                preg_match_all("/\benv\(\s*'([A-Z0-9_]+)'/", $file->getContents(), $matches);

                return $matches[1];
            })
            ->unique()
            ->values()
            ->all();
    };

    // Keys the stock Laravel and package config files already read were never meant to be documented here. The app
    // skeleton reads a few more that the framework's own copy of its config doesn't.
    $frameworkKeys = [
        ...$envKeys(glob(base_path('vendor/*/*/config'), GLOB_ONLYDIR)),
        'DB_SSLMODE',
        'POSTMARK_API_KEY',
        'RESEND_API_KEY',
    ];

    // A key counts as documented when it has a line, commented out or not
    $documented = file_get_contents(base_path('.env.example'));

    $missing = collect($envKeys([config_path(), database_path()]))
        ->diff($frameworkKeys)
        ->reject(fn (string $key): bool => preg_match("/^#?\s*{$key}=/m", $documented) === 1)
        ->sort()
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
