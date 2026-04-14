<?php

namespace App\Models\Show\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filter columns using operators like :eq, :gte, :lte, :null, :not_null, :exact, :not_eq.
 */
class MetadataFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): void
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->apply($query, $property, $val);
            }

            return;
        }

        $this->apply($query, $property, $value);
    }

    protected function apply(Builder $query, string $property, mixed $value): void
    {
        if (!is_string($value)) {
            $query->where($property, '=', $value);

            return;
        }

        if (str_contains($value, ':') || in_array($value, ['null', 'not_null'])) {
            $operator = $value;
            $actualValue = null;

            if (str_contains($value, ':')) {
                [$operator, $actualValue] = explode(':', $value, 2);
            }

            match ($operator) {
                'eq', 'exact' => $query->where($property, '=', $actualValue),
                'not_eq' => $query->where($property, '!=', $actualValue),
                'gte' => $query->where($property, '>=', $actualValue),
                'gt' => $query->where($property, '>', $actualValue),
                'lte' => $query->where($property, '<=', $actualValue),
                'lt' => $query->where($property, '<', $actualValue),
                'null' => $query->whereNull($property),
                'not_null' => $query->whereNotNull($property),
                'like' => $query->where($property, 'like', "%{$actualValue}%"),
                default => $query->where($property, '=', $value),
            };

            return;
        }

        // Default to exact match if no operator provided
        $query->where($property, '=', $value);
    }
}
