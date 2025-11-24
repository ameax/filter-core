<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Negated match: column != value or column NOT IN (values).
 */
class IsNotMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'is_not';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereNotIn($column, $value);
        } else {
            $query->where($column, '!=', $value);
        }
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        if (is_array($value)) {
            return $collection->whereNotIn($column, $value);
        }

        return $collection->where($column, '!=', $value);
    }
}
