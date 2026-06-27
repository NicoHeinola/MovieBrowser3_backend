<?php

namespace App\Models\Episode;

use App\Models\Episode\Query\HasEpisodeQuery;
use App\Models\Episode\Relations\HasEpisodeRelations;
use App\Models\Setting\Setting;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_entry_id', 'name', 'filename', 'sequence_number'])]
class Episode extends Model
{
    use HasEpisodeQuery;
    use HasEpisodeRelations;

    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    /**
     * @return Attribute<string, never>
     */
    protected function filePath(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->videoPath();
        });
    }

    public function videoDirectoryPath(
        ?string $basePath = null,
        ?string $showName = null,
        ?string $entryName = null,
    ): string {
        $entry = $this->entry;
        $show = $entry->show;
        $primaryTitle = $show->titles->firstWhere('is_primary', true)?->title;
        $resolvedShowName = $showName ?? $primaryTitle ?? (string) $show->id;

        $parts = [
            $this->normalizeBasePath($basePath ?? $this->videoBasePath()),
            $show->id.'_'.self::sanitizePathPart($resolvedShowName),
            $entry->id.'_'.self::sanitizePathPart($entryName ?? $entry->name),
        ];

        return implode('/', array_values(array_filter($parts, fn (string $value): bool => $value !== '')));
    }

    public function videoPath(
        ?string $basePath = null,
        ?string $showName = null,
        ?string $entryName = null,
        ?string $episodeName = null,
        ?string $filename = null,
    ): string {
        $directory = $this->videoDirectoryPath($basePath, $showName, $entryName);

        $resolvedFilename = $filename ?? $this->filename;

        return $resolvedFilename === ''
            ? $directory
            : "{$directory}/{$this->videoFilename($episodeName, $resolvedFilename)}";
    }

    public function videoFilename(?string $episodeName = null, ?string $filename = null): string
    {
        $episodeSegment = $this->id.'_'.self::sanitizePathPart($episodeName ?? $this->name);

        $resolvedFilename = $filename ?? $this->filename;
        $extension = pathinfo($resolvedFilename, PATHINFO_EXTENSION);

        return $extension === '' ? $episodeSegment : "{$episodeSegment}.{$extension}";
    }

    public function videoBasePath(): string
    {
        $basePath = Setting::query()->whereKey('video_base_path')->value('value');

        return $this->normalizeBasePath((string) ($basePath ?: storage_path('app/videos')));
    }

    private function normalizeBasePath(string $basePath): string
    {
        return rtrim($basePath, '/\\');
    }

    public static function pathIsAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\/]~', $path) === 1;
    }

    private static function sanitizePathPart(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\\\\\/]+/', '-', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return $value !== '' ? $value : 'untitled';
    }

    protected static function newFactory(): EpisodeFactory
    {
        return EpisodeFactory::new();
    }
}
