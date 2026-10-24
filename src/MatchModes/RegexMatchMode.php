<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Exceptions\UnsupportedMatchModeException;
use Ameax\FilterCore\Query\SqlDialect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Regex match: column matches regular expression pattern.
 *
 * Uses REGEXP on MySQL, MariaDB and SQLite, ~ on PostgreSQL. SQL Server has no
 * regular expressions before 2025; there the mode throws instead of failing in SQL.
 */
class RegexMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'regex';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $driver = SqlDialect::driver($query);
        $wrapped = ($query instanceof Builder ? $query->getQuery() : $query)->getGrammar()->wrap($column);

        match ($driver) {
            'pgsql' => $query->whereRaw($wrapped.' ~ ?', [$value]),
            'mysql', 'mariadb', 'sqlite' => $query->whereRaw($wrapped.' REGEXP ?', [$value]),
            default => throw new UnsupportedMatchModeException("Match mode 'regex' is not supported by the database driver '{$driver}'"),
        };
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
