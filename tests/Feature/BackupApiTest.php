<?php

use App\Exceptions\BackupFailedException;
use App\Jobs\CreateScheduledBackupJob;
use App\Models\User\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withToken;

beforeEach(function () {
    $this->backupPath = dirname(__DIR__, 2).'/storage/app/testing-backups';
    File::deleteDirectory($this->backupPath);
    File::ensureDirectoryExists($this->backupPath);

    config(['backups.path' => $this->backupPath, 'backups.keep' => 14, 'backups.interval_hours' => 24]);
    travelTo(now()->parse('2026-11-15 12:00:00'));
});

afterEach(function () {
    File::deleteDirectory($this->backupPath);
});

function putBackup(string $name, string $contents = 'dump'): string
{
    return putVideoFile(test()->backupPath."/{$name}", $contents);
}

/**
 * The backup folder's file names, sorted.
 *
 * @return list<string>
 */
function backupFileNames(): array
{
    return collect(File::files(test()->backupPath))->map->getFilename()->sort()->values()->all();
}

/**
 * pg_dump writing `$contents` to its --file, or failing with `$error`.
 */
function fakePgDump(string $contents = 'pg-dump-bytes', ?string $error = null): void
{
    Process::fake(function (PendingProcess $process) use ($contents, $error) {
        if ($error !== null) {
            return Process::result(errorOutput: $error, exitCode: 1);
        }

        $file = collect($process->command)->first(fn (string $argument): bool => str_starts_with($argument, '--file='));
        File::put(substr($file, strlen('--file=')), $contents);

        return Process::result();
    });
}

/**
 * The hourly job, the way `queue` runs it. Returns the dump it made, if any.
 */
function runScheduledBackup(): ?string
{
    $before = backupFileNames();
    app()->call([new CreateScheduledBackupJob, 'handle']);

    return array_values(array_diff(backupFileNames(), $before))[0] ?? null;
}

test('backup endpoints require an admin', function () {
    getJson('/api/v1/backups')->assertUnauthorized();
    postJson('/api/v1/backups')->assertUnauthorized();

    withToken(User::factory()->create()->createToken('test-token')->plainTextToken);

    getJson('/api/v1/backups')->assertForbidden();
    postJson('/api/v1/backups')->assertForbidden();
});

test('the list is empty before any backup', function () {
    actingAsAdmin();

    getJson('/api/v1/backups')->assertOk()->assertExactJson(['data' => []]);
});

test('the list has the dumps newest first and skips other files', function () {
    actingAsAdmin();
    putBackup('moviebrowser3-20261114-030000.dump', 'older');
    putBackup('moviebrowser3-20261115-030000-before-update.dump', 'newest');
    putBackup('moviebrowser3-20261115-040000.dump.abcdefgh.partial');
    putBackup('notes.txt');

    getJson('/api/v1/backups')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['name' => 'moviebrowser3-20261115-030000-before-update.dump', 'size' => 6, 'created_at' => '2026-11-15T03:00:00.000000Z'],
            ['name' => 'moviebrowser3-20261114-030000.dump', 'size' => 5, 'created_at' => '2026-11-14T03:00:00.000000Z'],
        ]]);
});

test('a missing backup folder lists no dumps', function () {
    actingAsAdmin();
    File::deleteDirectory($this->backupPath);

    getJson('/api/v1/backups')->assertOk()->assertExactJson(['data' => []]);
});

test('a backup dumps the database into the backup folder', function () {
    actingAsAdmin();
    fakePgDump('pg-dump-bytes');

    postJson('/api/v1/backups')
        ->assertCreated()
        ->assertExactJson(['name' => 'moviebrowser3-20261115-120000.dump', 'size' => 13, 'created_at' => '2026-11-15T12:00:00.000000Z']);

    expect(backupFileNames())->toBe(['moviebrowser3-20261115-120000.dump'])
        ->and(File::get("{$this->backupPath}/moviebrowser3-20261115-120000.dump"))->toBe('pg-dump-bytes');
    getJson('/api/v1/backups')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'moviebrowser3-20261115-120000.dump');
});

test('pg_dump gets the password through the environment, not its arguments', function () {
    actingAsAdmin();
    config(['database.connections.pgsql.password' => 'secret-password']);
    fakePgDump();

    postJson('/api/v1/backups')->assertCreated();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'pg_dump'
        && in_array('--format=custom', $process->command, true)
        && !str_contains(implode(' ', $process->command), 'secret-password')
        && $process->environment['PGPASSWORD'] === 'secret-password');
});

test('a failed dump answers backup_failed and leaves no file behind', function () {
    actingAsAdmin();
    fakePgDump(error: 'connection refused');

    postJson('/api/v1/backups')
        ->assertStatus(500)
        ->assertExactJson(['code' => 'backup_failed', 'params' => []]);

    expect(backupFileNames())->toBe([]);
});

test('a missing backup folder answers backup_dir_unavailable', function () {
    actingAsAdmin();
    fakePgDump();
    File::deleteDirectory($this->backupPath);

    postJson('/api/v1/backups')
        ->assertUnprocessable()
        ->assertExactJson(['code' => 'backup_dir_unavailable', 'params' => []]);

    Process::assertNothingRan();
});

test('a backup keeps only the newest dumps and never touches other files', function () {
    actingAsAdmin();
    config(['backups.keep' => 2]);
    fakePgDump();
    putBackup('moviebrowser3-20261113-030000.dump');
    putBackup('moviebrowser3-20261114-030000.dump');
    putBackup('notes.txt');
    // A dump killed long ago, and one that may still be running
    touch(putBackup('moviebrowser3-20261101-030000.dump.abcdefgh.partial'), now()->subDay()->getTimestamp());
    putBackup('moviebrowser3-20261115-115959.dump.ijklmnop.partial');

    postJson('/api/v1/backups')->assertCreated();

    expect(backupFileNames())->toBe([
        'moviebrowser3-20261114-030000.dump',
        'moviebrowser3-20261115-115959.dump.ijklmnop.partial',
        'moviebrowser3-20261115-120000.dump',
        'notes.txt',
    ]);
});

test('the schedule backs up only once the newest dump is older than the interval', function () {
    fakePgDump();
    putBackup('moviebrowser3-20261114-130000.dump');

    expect(runScheduledBackup())->toBeNull();
    Process::assertNothingRan();

    // Ten minutes early still counts, so the hourly tick doesn't slip an hour every day
    travelTo(now()->parse('2026-11-15 12:50:00'));

    expect(runScheduledBackup())->toBe('moviebrowser3-20261115-125000.dump');
});

test('the schedule backs up when there is no dump yet', function () {
    fakePgDump();

    expect(runScheduledBackup())->not->toBeNull()
        ->and(backupFileNames())->toBe(['moviebrowser3-20261115-120000.dump']);
});

test('the command backs up with a label', function () {
    fakePgDump();

    artisan('backups:create', ['--label' => 'before-update'])->assertSuccessful();

    expect(backupFileNames())->toBe(['moviebrowser3-20261115-120000-before-update.dump']);
});

test('the command can keep every older dump', function () {
    config(['backups.keep' => 1]);
    fakePgDump();
    putBackup('moviebrowser3-20261114-030000.dump');

    artisan('backups:create', ['--label' => 'before-restore', '--no-prune' => true])->assertSuccessful();

    expect(backupFileNames())->toBe(['moviebrowser3-20261114-030000.dump', 'moviebrowser3-20261115-120000-before-restore.dump']);
});

test('a failed scheduled backup is logged without failing the job', function () {
    fakePgDump(error: 'connection refused');
    Exceptions::fake();

    expect(runScheduledBackup())->toBeNull();
    Exceptions::assertReported(BackupFailedException::class);
});

test('the command fails when the backup folder is missing', function () {
    fakePgDump();
    File::deleteDirectory($this->backupPath);

    artisan('backups:create')->assertFailed();
});
