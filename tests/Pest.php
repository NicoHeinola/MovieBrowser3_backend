<?php

use App\Models\Setting\Setting;
use App\Models\User\User;
use App\Services\QBittorrent\QBittorrentConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Saloon\Data\MultipartValue;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // The seeded video_base_path is the real storage/app/videos; keep every test away from it.
    ->beforeEach(fn () => useTestVideoBasePath())
    ->afterEach(fn () => File::deleteDirectory(videoBasePathForTests()))
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

function actingAsAdmin(): User
{
    $user = User::factory()->admin()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    \Pest\Laravel\withToken($token);

    return $user;
}

function videoBasePathForTests(): string
{
    return dirname(__DIR__).'/storage/app/testing-video-base';
}

$GLOBALS['videoBasePath'] = videoBasePathForTests();

function useTestVideoBasePath(): string
{
    Setting::query()->whereKey('video_base_path')->update(['value' => videoBasePathForTests()]);

    return videoBasePathForTests();
}

function putVideoFile(string $path, string $contents = 'video-bytes'): string
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

/**
 * A minimal Matroska file holding only what a length read needs. With `$streamedClusterFirst` the Info element comes
 * after a cluster of unknown size, as in a streamed recording, so only the SeekHead says where it is. A `$title` goes
 * into Info before the length, as mkvmerge writes it.
 */
function matroskaVideo(?float $seconds, bool $streamedClusterFirst = false, int $timestampScale = 1_000_000, ?string $title = null): string
{
    $element = function (int $id, string $body): string {
        // Ids keep their leading bytes as is; sizes use the 8 byte form so every element is built the same way
        return ltrim(pack('N', $id), "\0")."\x01".substr(pack('J', strlen($body)), 1).$body;
    };

    $info = $element(0x1549A966, ($title === null ? '' : $element(0x7BA9, $title))
        .$element(0x2AD7B1, ltrim(pack('J', $timestampScale), "\0"))
        .($seconds === null ? '' : $element(0x4489, pack('E', $seconds * 1_000_000_000 / $timestampScale))));

    $header = $element(0x1A45DFA3, $element(0x4282, 'matroska'));
    $void = $element(0xEC, str_repeat("\0", 16));

    if (!$streamedClusterFirst) {
        return $header.ltrim(pack('N', 0x18538067), "\0")."\x01".substr(pack('J', strlen($void.$info)), 1).$void.$info;
    }

    // A cluster id followed by the all-ones "unknown" size can't be stepped over
    $cluster = "\x1F\x43\xB6\x75\xFF".str_repeat("\xA3", 32);
    $seekHead = fn (int $position): string => $element(0x114D9B74, $element(0x4DBB, $element(0x53AB, "\x15\x49\xA9\x66").$element(0x53AC, pack('J', $position))));
    $children = $seekHead(strlen($seekHead(0)) + strlen($cluster)).$cluster.$info;

    return $header.ltrim(pack('N', 0x18538067), "\0")."\x01".substr(pack('J', strlen($children)), 1).$children;
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A faked request to an outside system that never reaches it, as when nothing listens on the port.
 */
function saloonConnectionRefused(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $request) => new FatalRequestException(new RuntimeException('Connection refused'), $request));
}

/**
 * The requests Saloon sent while faked, oldest first, each with its form or multipart fields as `name => value`.
 *
 * @param  class-string|null  $requestClass  Only requests of this class
 * @return Collection<int, array{request: PendingRequest, fields: array<string, mixed>}>
 */
function saloonSent(?string $requestClass = null): Collection
{
    return collect(Saloon::mockClient()->getRecordedResponses())
        ->map(fn (Response $response): PendingRequest => $response->getPendingRequest())
        ->filter(fn (PendingRequest $request): bool => $requestClass === null || $request->getRequest() instanceof $requestClass)
        ->map(fn (PendingRequest $request): array => ['request' => $request, 'fields' => saloonFields($request)])
        ->values();
}

/**
 * A sent request's form or multipart fields as `name => value`, or its JSON body.
 *
 * @return array<string, mixed>
 */
function saloonFields(PendingRequest $request): array
{
    $body = $request->body()?->all() ?? [];

    return collect($body)->mapWithKeys(fn (mixed $value, string|int $key): array => $value instanceof MultipartValue
        ? [$value->name => $value->value]
        : [$key => $value])->all();
}

/**
 * Answers a faked request with each response in turn, then with `$whenEmpty` once they run out.
 */
function saloonSequence(array $responses, ?MockResponse $whenEmpty = null): Closure
{
    return function () use (&$responses, $whenEmpty): MockResponse {
        $next = array_shift($responses) ?? $whenEmpty;

        if ($next === null) {
            throw new RuntimeException('The faked response sequence ran out.');
        }

        return $next;
    };
}

/**
 * The full URLs Saloon sent while faked, query included the way a browser writes it (`one%20piece`), oldest first.
 *
 * @return list<string>
 */
function saloonSentUrls(): array
{
    return saloonSent()
        ->map(function (array $sent): string {
            $query = http_build_query($sent['request']->query()->all(), '', '&', PHP_QUERY_RFC3986);

            return $sent['request']->getUrl().($query === '' ? '' : "?{$query}");
        })
        ->all();
}

/**
 * Fakes qBittorrent's Web API: logins pass, and each endpoint answers from `$answers` (a body, a mock response, or a
 * closure that builds one). Endpoints without an answer get "Ok.".
 *
 * @param  array<string, mixed>  $answers  Keyed by endpoint without the `/api/v2/` prefix, such as `torrents/info`
 */
function fakeQBittorrentApi(array $answers = []): void
{
    Saloon::fake([QBittorrentConnector::class => function (PendingRequest $request) use ($answers): MockResponse {
        $endpoint = ltrim($request->getRequest()->resolveEndpoint(), '/');

        if ($endpoint === 'auth/login') {
            return MockResponse::make('Ok.', 200, ['Set-Cookie' => 'SID=session; path=/']);
        }

        $answer = $answers[$endpoint] ?? 'Ok.';

        return match (true) {
            $answer instanceof Closure => $answer($request),
            $answer instanceof MockResponse => $answer,
            default => MockResponse::make($answer),
        };
    }]);
}

/**
 * The fields of the requests sent to one qBittorrent endpoint.
 *
 * @return list<array<string, mixed>>
 */
function qBittorrentRequestsTo(string $endpoint): array
{
    return saloonSent()
        ->filter(fn (array $sent): bool => ltrim($sent['request']->getRequest()->resolveEndpoint(), '/') === $endpoint)
        ->map(fn (array $sent): array => $sent['fields'] === [] ? $sent['request']->query()->all() : $sent['fields'])
        ->values()
        ->all();
}
