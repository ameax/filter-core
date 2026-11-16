<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The name of a pond's koi, with its own query logic: it must run on the koi table,
 * which it finds through the query it is given.
 */
class PondKoiNameFilter extends KoiNameFilter
{
    public function relationScope(): ?string
    {
        return 'kois';
    }

    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        $table = $query instanceof Builder ? $query->getModel()->getTable() : (string) $query->from;
        $query->where($table.'.name', '=', $value);

        return true;
    }
}
