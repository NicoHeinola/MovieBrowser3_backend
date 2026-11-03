<?php

namespace App\Models\Query;

use Illuminate\Database\Eloquent\Builder;

/**
 * Case-insensitive "contains" matching for search scopes. Lowercases both sides, so it behaves the same on
 * Postgres (case-sensitive like) and SQLite.
 */
final class CaseInsensitiveLike
{
    /**
     * Rows whose column contains the term, ignoring case; % and _ in the term match literally.
     */
    public static function where(Builder $query, string $column, string $term, string $boolean = 'and'): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        $query->whereRaw("lower({$column}) like ? escape '!'", [$pattern], $boolean);
    }
}
