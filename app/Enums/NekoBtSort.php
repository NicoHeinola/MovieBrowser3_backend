<?php

namespace App\Enums;

/**
 * The fields a nekoBT search can be sorted by. `Best` is nekoBT's relevance order and has no direction.
 */
enum NekoBtSort: string
{
    case Best = 'best';
    case Date = 'date';
    case Size = 'size';
    case Seeders = 'seeders';
    case Leechers = 'leechers';
    case Downloads = 'downloads';
    case Comments = 'comments';

    /**
     * Every `sort` value the API accepts: each field ascending and with a leading `-` for descending, except `best`.
     *
     * @return list<string>
     */
    public static function queryValues(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $sort): array => $sort === self::Best ? [$sort->value] : [$sort->value, "-{$sort->value}"])
            ->values()
            ->all();
    }
}
