<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * All match: all values must be present.
 *
 * For regular columns with a single value this behaves like IS.
 * For multiple values on a non-array column, this is impossible (returns no results).
 *
 * This mode is primarily useful for:
 * - JSON array columns (whereJsonContains for each value)
 * - Many-to-many relations (whereHas for each value)
 */
class AllMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'all';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];

        if (count($values) === 1) {
            // Single value: behave like IS
            $query->where($column, $values[0]);

            return;
        }

        // Multiple values on a regular column is impossible
        // A single column can't equal multiple values simultaneously
        // Return no results by adding an impossible condition
        $query->whereRaw('1 = 0');
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        $values = is_array($value) ? $value : [$value];

        if (count($values) === 1) {
            return $collection->where($column, $values[0]);
        }

        // Multiple values on a regular column is impossible
        return $collection->filter(fn () => false);
    }
}
