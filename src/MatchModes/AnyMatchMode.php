<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Any of the values: column IN (values).
 */
class AnyMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'any';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn($column, $values);
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        $values = is_array($value) ? $value : [$value];

        return $collection->whereIn($column, $values);
    }
}
