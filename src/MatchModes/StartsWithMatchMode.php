<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Starts with match: column LIKE 'value%'.
 */
class StartsWithMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'startsWith';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->where($column, 'LIKE', $value.'%');
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->filter(function ($item) use ($column, $value) {
            $itemValue = data_get($item, $column);

            return $itemValue !== null && str_starts_with((string) $itemValue, (string) $value);
        });
    }
}
