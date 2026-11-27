<?php

namespace App\Dtos\AppVersion;

use Spatie\LaravelData\Data;

class ReleaseData extends Data
{
    /**
     * @param  string  $version  `X.Y.Z`
     * @param  string  $url  Its page on GitHub
     */
    public function __construct(
        public string $version,
        public string $url,
    ) {}

    /**
     * @param  array<string, mixed>  $release  A release as GitHub describes it
     * @return self|null Null when its tag isn't `vX.Y.Z`, so there's no version to compare
     */
    public static function fromGitHub(array $release): ?self
    {
        $version = ltrim((string) ($release['tag_name'] ?? ''), 'v');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return null;
        }

        return new self($version, $release['html_url']);
    }
}
