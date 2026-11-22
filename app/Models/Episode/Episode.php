<?php

namespace App\Models\Episode;

use App\Models\Episode\Query\HasEpisodeQuery;
use App\Models\Episode\Relations\HasEpisodeRelations;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['show_entry_id', 'name', 'filename', 'has_video_file', 'video_file_size', 'video_duration_seconds', 'video_duration_queued_at', 'sequence_number'])]
class Episode extends Model
{
    use HasEpisodeQuery;
    use HasEpisodeRelations;

    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    protected $casts = [
        'has_video_file' => 'boolean',
        'video_file_size' => 'integer',
        'video_duration_seconds' => 'integer',
        'video_duration_queued_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): EpisodeFactory
    {
        return EpisodeFactory::new();
    }
}
