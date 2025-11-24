<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Null check: column IS NULL.
 */
class EmptyMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'empty';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->whereNull($column);
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->whereNull($column);
    }
}
