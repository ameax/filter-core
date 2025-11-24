<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
}
