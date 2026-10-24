<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Query\SqlDialect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Ends with match: column LIKE '%value'.
 */
class EndsWithMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'endsWith';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        SqlDialect::whereLike($query, $column, '%'.SqlDialect::escapeLike((string) $value));
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->filter(function ($item) use ($column, $value) {
            $itemValue = data_get($item, $column);

            return $itemValue !== null && str_ends_with((string) $itemValue, (string) $value);
        });
    }
}
