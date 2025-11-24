<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Greater than comparison: column > value.
 */
class GreaterThanMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'gt';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->where($column, '>', $value);
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->where($column, '>', $value);
    }
}
