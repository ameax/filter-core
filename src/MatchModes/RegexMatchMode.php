<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Regex match: column matches regular expression pattern.
 *
 * Uses MySQL REGEXP / PostgreSQL ~ operator.
 */
class RegexMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'regex';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // MySQL uses REGEXP, PostgreSQL uses ~
        // Laravel's grammar will handle the appropriate operator
        $query->whereRaw("$column REGEXP ?", [$value]);
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->filter(function ($item) use ($column, $value) {
            $itemValue = data_get($item, $column);

            if ($itemValue === null) {
                return false;
            }

            return preg_match('/'.$value.'/', (string) $itemValue) === 1;
        });
    }
}
