<?php

namespace App\Models\Episode;

use App\Models\Episode\Query\HasEpisodeQuery;
use App\Models\Episode\Relations\HasEpisodeRelations;
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
            $entry = $this->entry;
            $show = $entry->show;
            $primaryTitle = $show->titles->firstWhere('is_primary', true);
            $showName = $primaryTitle?->title ?? (string) $show->id;

            return "{$showName}/{$entry->name}/{$this->filename}";
        });
    }

    protected static function newFactory(): EpisodeFactory
    {
        return EpisodeFactory::new();
    }
}
