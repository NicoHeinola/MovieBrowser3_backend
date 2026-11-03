<?php

/**
 * Runs git in the repo and returns its trimmed output, or null when git fails (no git, not a clone, no tag).
 */
function gitOutput(string $arguments): ?string
{
    exec('git -C '.escapeshellarg(dirname(__DIR__, 2)).' '.$arguments.' 2>/dev/null', $lines, $status);

    return $status === 0 ? trim(implode("\n", $lines)) : null;
}

/**
 * The single step from one version to the next ('patch', 'minor' or 'major'), or null when it isn't exactly one.
 */
function versionStep(string $previous, string $next): ?string
{
    [$major, $minor, $patch] = array_map('intval', explode('.', $previous));

    return match ($next) {
        "{$major}.{$minor}.".($patch + 1) => 'patch',
        "{$major}.".($minor + 1).'.0' => 'minor',
        ($major + 1).'.0.0' => 'major',
        default => null,
    };
}

/**
 * The step the commits since the last release need: a breaking change (type!:) a major, a feat a minor, anything else a patch.
 */
function requiredVersionStep(string $subjects): string
{
    if (preg_match('/^\w+(\([^)]*\))?!:/m', $subjects)) {
        return 'major';
    }

    return preg_match('/^feat(\([^)]*\))?:/m', $subjects) ? 'minor' : 'patch';
}

$version = trim((string) file_get_contents(dirname(__DIR__, 2).'/VERSION'));

test('VERSION is a plain MAJOR.MINOR.PATCH version', function () use ($version) {
    expect($version)->toMatch('/^\d+\.\d+\.\d+$/');
});

test('VERSION is one release step above the last release tag, sized by the commits since it', function () use ($version) {
    if (gitOutput('rev-parse --git-dir') === null) {
        $this->markTestSkipped('Not a git clone.');
    }

    // The nearest v* tag behind HEAD is the last release this code builds on
    $tag = gitOutput("describe --tags --abbrev=0 --match 'v[0-9]*'");

    if ($tag === null) {
        expect($version)->toBe('1.0.0', 'Before the first release, VERSION is 1.0.0');

        return;
    }

    $released = substr($tag, 1);

    // Right on the release commit VERSION still equals the tag
    if ($version === $released && gitOutput('describe --tags --exact-match HEAD') === $tag) {
        return;
    }

    $step = versionStep($released, $version);
    expect($step)->not->toBeNull("VERSION {$version} must be exactly one patch, minor or major step above release {$tag}");

    $order = ['patch' => 0, 'minor' => 1, 'major' => 2];
    $required = requiredVersionStep((string) gitOutput("log --format=%s {$tag}..HEAD"));
    expect($order[$step])->toBeGreaterThanOrEqual($order[$required], "Commits since {$tag} need a {$required} step, VERSION {$version} is a {$step} step");
});

test('version steps allow exactly one patch, minor or major step', function () {
    expect(versionStep('1.0.0', '1.0.1'))->toBe('patch')
        ->and(versionStep('1.0.0', '1.1.0'))->toBe('minor')
        ->and(versionStep('1.0.0', '2.0.0'))->toBe('major')
        ->and(versionStep('1.0.0', '1.0.2'))->toBeNull()
        ->and(versionStep('1.0.1', '1.1.1'))->toBeNull()
        ->and(versionStep('1.0.0', '1.0.0'))->toBeNull();
});

test('commit types decide the required version step', function () {
    expect(requiredVersionStep("fix(shows): a\nchore: b"))->toBe('patch')
        ->and(requiredVersionStep("fix: a\nfeat(#33): b"))->toBe('minor')
        ->and(requiredVersionStep("feat: a\nfeat(api)!: rename"))->toBe('major')
        ->and(requiredVersionStep('refactor!: drop endpoint'))->toBe('major');
});
