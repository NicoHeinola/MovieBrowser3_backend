<?php

namespace App\Models\Show\Query\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RandomSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->inRandomOrder();
    }
}
