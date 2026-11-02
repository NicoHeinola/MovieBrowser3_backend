<?php

namespace App\Models\User\Query;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

trait HasUserQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            'username',
            AllowedFilter::exact('is_admin'),
        ];
    }

    public function scopeUsernameIgnoringCase(Builder $query, string $username): void
    {
        $query->whereRaw('lower(username) = ?', [mb_strtolower($username)]);
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedSorts(): array
    {
        return [
            'id',
            'username',
            'is_admin',
            'created_at',
            'updated_at',
        ];
    }
}
