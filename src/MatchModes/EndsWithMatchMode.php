<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
        $query->where($column, 'LIKE', '%'.$value);
    }
}
