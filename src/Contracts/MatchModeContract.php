<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Contract for match mode implementations.
 *
 * Match modes define how filter values are compared against database columns
 * or collection items. Implement this interface to create custom match modes.
 */
interface MatchModeContract
{
    /**
     * Get the unique key for this match mode.
     *
     * Used for serialization and identification.
     */
    public function key(): string;

    /**
     * Apply this match mode to a query.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void;

    /**
     * Apply this match mode to a collection.
     *
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     */
    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection;
}
