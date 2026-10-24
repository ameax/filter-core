<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Matches nothing. Used by NeverFilter.
 *
 * @internal
 */
final class NeverMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'never';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->whereRaw('1 = 0');
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->filter(fn () => false);
    }
}
