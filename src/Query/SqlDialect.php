<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Query;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Database differences the match modes have to respect.
 *
 * Supported: MySQL, MariaDB, SQL Server, PostgreSQL, SQLite.
 *
 * @internal
 */
final class SqlDialect
{
    /**
     * Escape character for LIKE patterns. Given explicitly with ESCAPE, because the
     * default differs: MySQL uses a backslash, SQL Server and SQLite have none.
     */
    public const LIKE_ESCAPE = '!';

    /**
     * Earliest date MySQL accepts as a literal in every sql_mode. Comparing against it
     * excludes the zero date without writing '0000-00-00', which strict modes reject.
     */
    private const FIRST_VALID_DATE = '1000-01-01';

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function driver(Builder|QueryBuilder $query): string
    {
        $connection = self::base($query)->getConnection();

        return $connection instanceof Connection ? $connection->getDriverName() : '';
    }

    /**
     * MySQL and MariaDB can store the zero date 0000-00-00.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function allowsZeroDates(Builder|QueryBuilder $query): bool
    {
        return in_array(self::driver($query), ['mysql', 'mariadb'], true);
    }

    /**
     * Treat the zero date like NULL: legacy schemas store "no date" as 0000-00-00, and
     * it sorts before every real date. Without this, "older than 180 days" or
     * "before 2020" would match every record that has no date at all.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function excludeZeroDates(Builder|QueryBuilder $query, string $column): void
    {
        if (! self::allowsZeroDates($query)) {
            return;
        }

        $query->where($column, '>=', self::FIRST_VALID_DATE);
    }

    /**
     * Escape LIKE wildcards in user input, so that "50%" means the characters 5, 0, %.
     *
     * The bracket is escaped too: SQL Server reads [abc] as a character class.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_', '['],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_', self::LIKE_ESCAPE.'['],
            $value
        );
    }

    /**
     * Add `column LIKE pattern ESCAPE '!'`; the pattern must already be escaped.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function whereLike(Builder|QueryBuilder $query, string $column, string $pattern, bool $not = false): void
    {
        $wrapped = self::base($query)->getGrammar()->wrap($column);

        $query->whereRaw(
            $wrapped.($not ? ' NOT' : '').' LIKE ? ESCAPE ?',
            [$pattern, self::LIKE_ESCAPE]
        );
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    private static function base(Builder|QueryBuilder $query): QueryBuilder
    {
        return $query instanceof Builder ? $query->getQuery() : $query;
    }
}
