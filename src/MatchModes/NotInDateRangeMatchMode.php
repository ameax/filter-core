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
 * Match mode for "not in date range" filtering.
 *
 * Inverse of DateRangeMatchMode - matches records outside the given range.
 */
class NotInDateRangeMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'notInDateRange';
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $resolved = $this->resolveValue($value);

        if ($resolved->start !== null && $resolved->end !== null) {
            // Closed range: NOT BETWEEN = column < start OR column > end
            $query->where(function ($q) use ($column, $resolved): void {
                $q->where($column, '<', $resolved->start->toDateTimeString())
                    ->orWhere($column, '>', $resolved->end->toDateTimeString());
            });
        } elseif ($resolved->start === null && $resolved->end !== null) {
            // Open start (original: <= end), inverse: > end
            $query->where($column, '>', $resolved->end->toDateTimeString());
        } elseif ($resolved->start !== null && $resolved->end === null) {
            // Open end (original: >= start), inverse: < start
            $query->where($column, '<', $resolved->start->toDateTimeString());
        } else {
            // Unbounded: original matches everything, inverse matches nothing
            // We handle this by adding an impossible condition
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     */
    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        $resolved = $this->resolveValue($value);

        // Unbounded range - inverse matches nothing
        if ($resolved->isUnbounded()) {
            return $collection->reject(fn () => true);
        }

        return $collection->filter(function ($item) use ($column, $resolved): bool {
            $itemValue = data_get($item, $column);

            if ($itemValue === null) {
                return true; // Null values are "outside" the range
            }

            $itemDate = $itemValue instanceof Carbon
                ? $itemValue
                : Carbon::parse($itemValue);

            // Return true if NOT in the range
            return ! $resolved->contains($itemDate);
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
            'NotInDateRangeMatchMode requires DateRangeValue, ResolvedDateRange, or array with start/end'
        );
    }
}
