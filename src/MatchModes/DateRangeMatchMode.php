<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\ResolvedDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Match mode for date range filtering.
 *
 * Accepts:
 * - DateRangeValue object (will be resolved)
 * - ResolvedDateRange object (already resolved)
 * - Array with 'start' and 'end' keys (dates as strings)
 */
class DateRangeMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'dateRange';
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $resolved = $this->resolveValue($value);

        // Handle different range types
        if ($resolved->start !== null && $resolved->end !== null) {
            // Closed range: BETWEEN start AND end
            $query->whereBetween($column, [
                $resolved->start->toDateTimeString(),
                $resolved->end->toDateTimeString(),
            ]);
        } elseif ($resolved->start === null && $resolved->end !== null) {
            // Open start: column <= end
            $query->where($column, '<=', $resolved->end->toDateTimeString());
        } elseif ($resolved->start !== null && $resolved->end === null) {
            // Open end: column >= start
            $query->where($column, '>=', $resolved->start->toDateTimeString());
        }
        // Unbounded: no conditions (matches everything)
    }

    /**
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     */
    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        $resolved = $this->resolveValue($value);

        return $collection->filter(function ($item) use ($column, $resolved): bool {
            $itemValue = data_get($item, $column);

            if ($itemValue === null) {
                return false;
            }

            $itemDate = $itemValue instanceof Carbon
                ? $itemValue
                : Carbon::parse($itemValue);

            return $resolved->contains($itemDate);
        });
    }

    /**
     * Resolve the value to a ResolvedDateRange.
     */
    protected function resolveValue(mixed $value): ResolvedDateRange
    {
        if ($value instanceof ResolvedDateRange) {
            return $value;
        }

        if ($value instanceof DateRangeValue) {
            return $value->resolve();
        }

        if (is_array($value)) {
            $start = isset($value['start']) ? Carbon::parse($value['start'])->startOfDay() : null;
            $end = isset($value['end']) ? Carbon::parse($value['end'])->endOfDay() : null;

            return new ResolvedDateRange($start, $end);
        }

        throw new InvalidArgumentException(
            'DateRangeMatchMode requires DateRangeValue, ResolvedDateRange, or array with start/end'
        );
    }
}
