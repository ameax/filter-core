<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\DateRange\DateRangeOptions;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\ResolvedDateRange;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicDateFilter;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Base class for DATE and DATETIME type filters.
 *
 * Supports filtering date/datetime columns with:
 * - Quick selections (today, last week, this month, etc.)
 * - Relative ranges (last 30 days, next 2 weeks)
 * - Specific periods (January 2023, Q4 last year)
 * - Annual ranges (fiscal year, academic year)
 * - Custom date ranges (user-defined start/end)
 * - Expression syntax (power users)
 * - Direction restrictions (past only, future only)
 * - Timezone handling for DATETIME columns (via hasTime())
 */
abstract class DateFilter extends Filter
{
    /**
     * Create a dynamic date filter with the given key.
     */
    public static function dynamic(string $key): DynamicDateFilter
    {
        return DynamicDateFilter::create($key);
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATE;
    }

    /**
     * Allowed directions for this filter.
     *
     * Override in subclass to restrict to past or future only.
     * Return null to allow all directions.
     *
     * @return array<DateDirection>|null
     */
    public function allowedDirections(): ?array
    {
        return null; // All directions allowed by default
    }

    /**
     * Whether to include "today" option even if only FUTURE is allowed.
     */
    public function allowToday(): bool
    {
        return true;
    }

    /**
     * Whether the column stores time (DATETIME/TIMESTAMP) or just date (DATE).
     *
     * When true, timezone conversion is applied:
     * - User's "today" in Europe/Berlin becomes UTC times in query
     * - Example: 2024-11-15 in Berlin = 2024-11-14 23:00:00 to 2024-11-15 22:59:59 UTC
     *
     * When false (default), simple date boundaries are used:
     * - 2024-11-15 00:00:00 to 2024-11-15 23:59:59
     */
    public function hasTime(): bool
    {
        return false;
    }

    /**
     * Get the timezone for query conversion.
     *
     * Returns the configured timezone or falls back to app timezone.
     */
    protected function getQueryTimezone(): string
    {
        return config('filter-core.timezone')
            ?? config('app.timezone', 'UTC');
    }

    /**
     * Apply date range filter with timezone handling for datetime columns.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        // Only handle date range modes
        if (! ($mode instanceof DateRangeMatchMode) && ! ($mode instanceof NotInDateRangeMatchMode)) {
            return false;
        }

        // Resolve the date range with timezone handling
        $resolved = $this->resolveForQuery($value);

        if ($resolved === null) {
            return false;
        }

        $column = $this->column();
        $isNot = $mode instanceof NotInDateRangeMatchMode;

        // Apply the resolved range
        if ($resolved->start !== null && $resolved->end !== null) {
            if ($isNot) {
                $query->whereNotBetween($column, [
                    $resolved->start->toDateTimeString(),
                    $resolved->end->toDateTimeString(),
                ]);
            } else {
                $query->whereBetween($column, [
                    $resolved->start->toDateTimeString(),
                    $resolved->end->toDateTimeString(),
                ]);
            }
        } elseif ($resolved->start === null && $resolved->end !== null) {
            $operator = $isNot ? '>' : '<=';
            $query->where($column, $operator, $resolved->end->toDateTimeString());
        } elseif ($resolved->start !== null && $resolved->end === null) {
            $operator = $isNot ? '<' : '>=';
            $query->where($column, $operator, $resolved->start->toDateTimeString());
        }

        return true;
    }

    /**
     * Resolve a date range value for query execution.
     *
     * Handles timezone conversion for datetime columns.
     */
    protected function resolveForQuery(mixed $value): ?ResolvedDateRange
    {
        if ($value instanceof ResolvedDateRange) {
            return $value;
        }

        if ($value instanceof DateRangeValue) {
            $resolved = $value->resolve();

            // Apply timezone conversion for datetime columns
            if ($this->hasTime() && ($resolved->start !== null || $resolved->end !== null)) {
                return $this->convertToUtc($resolved);
            }

            return $resolved;
        }

        if (is_array($value)) {
            $start = isset($value['start']) ? Carbon::parse($value['start'])->startOfDay() : null;
            $end = isset($value['end']) ? Carbon::parse($value['end'])->endOfDay() : null;

            $resolved = new ResolvedDateRange($start, $end);

            if ($this->hasTime() && ($start !== null || $end !== null)) {
                return $this->convertToUtc($resolved);
            }

            return $resolved;
        }

        return null;
    }

    /**
     * Convert a resolved date range from user timezone to UTC.
     *
     * Example for Europe/Berlin (UTC+1 in winter):
     * - Input: 2024-11-15 00:00:00 to 2024-11-15 23:59:59 (Berlin)
     * - Output: 2024-11-14 23:00:00 to 2024-11-15 22:59:59 (UTC)
     */
    protected function convertToUtc(ResolvedDateRange $resolved): ResolvedDateRange
    {
        $timezone = $this->getQueryTimezone();

        $start = $resolved->start;
        $end = $resolved->end;

        if ($start !== null) {
            // Set timezone to user timezone, then convert to UTC
            $start = $start->copy()->shiftTimezone($timezone)->utc();
        }

        if ($end !== null) {
            // Set timezone to user timezone, then convert to UTC
            $end = $end->copy()->shiftTimezone($timezone)->utc();
        }

        return new ResolvedDateRange($start, $end);
    }

    public function defaultMode(): MatchModeContract
    {
        return new DateRangeMatchMode;
    }

    /**
     * @return array<MatchModeContract>
     */
    public function allowedModes(): array
    {
        return [
            new DateRangeMatchMode,
            new NotInDateRangeMatchMode,
        ];
    }

    /**
     * Sanitize date range values.
     *
     * Converts arrays to DateRangeValue objects.
     */
    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if ($value === null) {
            return null;
        }

        // Already a DateRangeValue
        if ($value instanceof DateRangeValue) {
            return $value;
        }

        // Array format - convert to DateRangeValue
        if (is_array($value) && isset($value['type'])) {
            return DateRangeValue::fromArray($value);
        }

        // Simple array with start/end - treat as custom range
        if (is_array($value) && (isset($value['start']) || isset($value['end']))) {
            return $value; // Pass through to match mode
        }

        return $value;
    }

    /**
     * Get the quick date range options for UI.
     *
     * @return array<string, string>
     */
    public function getQuickOptions(): array
    {
        return DateRangeOptions::getQuickOptions(
            $this->allowedDirections(),
            $this->allowToday()
        );
    }

    /**
     * Get grouped quick options for UI.
     *
     * @return array<string, array<string, string>>
     */
    public function getGroupedQuickOptions(): array
    {
        return DateRangeOptions::getGroupedQuickOptions($this->allowedDirections());
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        // Empty/notEmpty modes don't require a value
        if (in_array($mode->key(), ['empty', 'notEmpty', 'not_empty'], true)) {
            return [];
        }

        // DateRangeValue or array with type
        return [
            'value' => ['required'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'allowedDirections' => $this->allowedDirections()
                ? array_map(fn (DateDirection $d) => $d->value, $this->allowedDirections())
                : null,
            'allowToday' => $this->allowToday(),
            'hasTime' => $this->hasTime(),
            'quickOptions' => $this->getQuickOptions(),
        ];
    }
}
