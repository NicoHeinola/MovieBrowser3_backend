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
        if (str_contains($property, '.')) {
            [$relation, $column] = collect(explode('.', $property))->pipe(fn ($parts) => [
                $parts->slice(0, -1)->implode('.'),
                $parts->last(),
            ]);

            $query->whereHas($relation, function (Builder $related) use ($column, $value) {
                $this->applyCondition($related, $column, $value);
            });

            return;
        }

        $this->applyCondition($query, $property, $value);
    }

    protected function applyCondition(Builder $query, string $column, mixed $value): void
    {
        if (!is_string($value)) {
            $query->where($column, '=', $value);

            return;
        }

        if (str_contains($value, ':') || in_array($value, ['null', 'not_null'])) {
            $operator = $value;
            $actualValue = null;

            if (str_contains($value, ':')) {
                [$operator, $actualValue] = explode(':', $value, 2);
            }

            match ($operator) {
                'eq', 'exact' => $query->where($column, '=', $actualValue),
                'not_eq' => $query->where($column, '!=', $actualValue),
                'gte' => $query->where($column, '>=', $actualValue),
                'gt' => $query->where($column, '>', $actualValue),
                'lte' => $query->where($column, '<=', $actualValue),
                'lt' => $query->where($column, '<', $actualValue),
                'null' => $query->whereNull($column),
                'not_null' => $query->whereNotNull($column),
                'like' => $query->where($column, 'like', "%{$actualValue}%"),
                default => $query->where($column, '=', $value),
            };

            return;
        }

        // Default to exact match if no operator provided
        $query->where($column, '=', $value);
    }
}
