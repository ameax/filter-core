<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\BetweenValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Range match: column BETWEEN min AND max.
 */
class BetweenMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'between';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if ($value instanceof BetweenValue) {
            $query->whereBetween($column, [$value->min, $value->max]);

            return;
        }

        if (is_array($value)) {
            $min = $value['min'] ?? $value[0] ?? null;
            $max = $value['max'] ?? $value[1] ?? null;

            if ($min === null || $max === null) {
                throw new InvalidArgumentException('Between match mode requires min and max values');
            }

            $query->whereBetween($column, [$min, $max]);

            return;
        }

        throw new InvalidArgumentException('Between match mode requires array or BetweenValue');
    }
}
