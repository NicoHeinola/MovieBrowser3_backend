<?php

namespace App\Models\Episode;

use App\Models\Episode\Query\HasEpisodeQuery;
use App\Models\Episode\Relations\HasEpisodeRelations;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_entry_id', 'name', 'filename', 'sequence_number'])]
class Episode extends Model
{
    use HasEpisodeQuery;
    use HasEpisodeRelations;

    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    protected static function newFactory(): EpisodeFactory
    {
        return EpisodeFactory::new();
    }
}
