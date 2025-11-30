# TODO: DateFilter Type

**Priority:** High
**Status:** Completed

## Problem

No built-in filter type for date columns (`DATE`, `DATETIME`). Users need flexible, user-friendly date selection including:
- Absolute dates (specific date or range)
- Relative dates ("last 30 days", "last month")
- Specific periods with offset ("January 3 years ago", "Q4 last year")
- Annual ranges ("fiscal year starting 3 years ago")

## Solution Overview

A comprehensive date filtering system with:
1. `DateRangeValue` - Universal DTO for all date range types
2. `ResolvedDateRange` - Resolved Carbon start/end dates
3. `DateFilter` - Base filter class with direction restrictions
4. `DateRangeMatchMode` - Universal match mode for date queries

---

## Core Components

### 1. Enums

```php
<?php

namespace Ameax\FilterCore\DateRange;

/**
 * Main categories of date range selection
 */
enum DateRangeType: string
{
    case QUICK = 'quick';           // Predefined: today, last_week, this_month
    case RELATIVE = 'relative';      // Last/Next X units (range)
    case SPECIFIC = 'specific';      // Specific period: month, quarter, week with offset
    case ANNUAL_RANGE = 'annual_range';  // Cross-year patterns: fiscal year
    case CUSTOM = 'custom';          // User-defined start/end dates
    case EXPRESSION = 'expression';  // Power user: ExpressionLanguage syntax
}

/**
 * Time units for date calculations
 */
enum DateUnit: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';

    public function labelSingular(): string
    {
        return match($this) {
            self::DAY => 'Tag',
            self::WEEK => 'Woche',
            self::MONTH => 'Monat',
            self::QUARTER => 'Quartal',
            self::YEAR => 'Jahr',
        };
    }

    public function labelPlural(): string
    {
        return match($this) {
            self::DAY => 'Tage',
            self::WEEK => 'Wochen',
            self::MONTH => 'Monate',
            self::QUARTER => 'Quartale',
            self::YEAR => 'Jahre',
        };
    }
}

/**
 * Direction for relative date ranges
 */
enum DateDirection: string
{
    case PAST = 'past';
    case FUTURE = 'future';

    public function label(): string
    {
        return match($this) {
            self::PAST => 'Vergangenheit',
            self::FUTURE => 'Zukunft',
        };
    }
}

/**
 * Quick selection presets
 */
enum QuickDateRange: string
{
    // Days
    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case TOMORROW = 'tomorrow';

    // Weeks
    case THIS_WEEK = 'this_week';
    case LAST_WEEK = 'last_week';
    case NEXT_WEEK = 'next_week';

    // Months
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case NEXT_MONTH = 'next_month';

    // Quarters
    case THIS_QUARTER = 'this_quarter';
    case LAST_QUARTER = 'last_quarter';
    case NEXT_QUARTER = 'next_quarter';

    // Years
    case THIS_YEAR = 'this_year';
    case LAST_YEAR = 'last_year';
    case NEXT_YEAR = 'next_year';

    // Half Years
    case THIS_HALF_YEAR = 'this_half_year';
    case LAST_HALF_YEAR = 'last_half_year';
    case H1_THIS_YEAR = 'h1_this_year';
    case H2_THIS_YEAR = 'h2_this_year';
    case H1_LAST_YEAR = 'h1_last_year';
    case H2_LAST_YEAR = 'h2_last_year';

    // Special
    case YEAR_TO_DATE = 'year_to_date';
    case LAST_12_MONTHS = 'last_12_months';

    public function label(): string
    {
        return match($this) {
            self::TODAY => 'Heute',
            self::YESTERDAY => 'Gestern',
            self::TOMORROW => 'Morgen',
            self::THIS_WEEK => 'Diese Woche',
            self::LAST_WEEK => 'Letzte Woche',
            self::NEXT_WEEK => 'Nächste Woche',
            self::THIS_MONTH => 'Dieser Monat',
            self::LAST_MONTH => 'Letzter Monat',
            self::NEXT_MONTH => 'Nächster Monat',
            self::THIS_QUARTER => 'Dieses Quartal',
            self::LAST_QUARTER => 'Letztes Quartal',
            self::NEXT_QUARTER => 'Nächstes Quartal',
            self::THIS_YEAR => 'Dieses Jahr',
            self::LAST_YEAR => 'Letztes Jahr',
            self::NEXT_YEAR => 'Nächstes Jahr',
            self::THIS_HALF_YEAR => 'Dieses Halbjahr',
            self::LAST_HALF_YEAR => 'Letztes Halbjahr',
            self::H1_THIS_YEAR => 'H1 dieses Jahr',
            self::H2_THIS_YEAR => 'H2 dieses Jahr',
            self::H1_LAST_YEAR => 'H1 letztes Jahr',
            self::H2_LAST_YEAR => 'H2 letztes Jahr',
            self::YEAR_TO_DATE => 'Jahr bis heute',
            self::LAST_12_MONTHS => 'Letzte 12 Monate',
        };
    }

    public function direction(): DateDirection
    {
        return match($this) {
            self::TOMORROW, self::NEXT_WEEK, self::NEXT_MONTH,
            self::NEXT_QUARTER, self::NEXT_YEAR => DateDirection::FUTURE,
            default => DateDirection::PAST,
        };
    }
}
```

### 2. DateRangeValue DTO

```php
<?php

namespace Ameax\FilterCore\DateRange;

use Carbon\Carbon;

/**
 * Universal DTO for all date range selections.
 * Serializable to JSON for filter persistence.
 */
class DateRangeValue
{
    private function __construct(
        public readonly DateRangeType $type,

        // QUICK
        public readonly ?QuickDateRange $quick = null,

        // RELATIVE (range over multiple units)
        public readonly ?int $amount = null,
        public readonly ?DateUnit $unit = null,
        public readonly ?DateDirection $direction = null,
        public readonly bool $includePartial = true,

        // SPECIFIC (single unit with offset)
        public readonly ?int $month = null,        // 1-12
        public readonly ?int $quarter = null,      // 1-4
        public readonly ?int $week = null,         // 1-53
        public readonly ?int $year = null,         // Absolute: 2024
        public readonly ?int $yearOffset = null,   // Relative: -3 = 3 years ago
        public readonly ?int $unitOffset = null,   // For sub-year units: -2 = 2 months ago

        // ANNUAL_RANGE (cross-year patterns)
        public readonly ?int $startMonth = null,
        public readonly ?int $startDay = null,
        public readonly ?int $endMonth = null,
        public readonly ?int $endDay = null,
        public readonly ?int $startYearOffset = null,

        // CUSTOM
        public readonly ?string $startDate = null,  // Y-m-d or null (open)
        public readonly ?string $endDate = null,    // Y-m-d or null (open)
    ) {}

    // ================================================================
    // QUICK SELECTION
    // ================================================================

    public static function quick(QuickDateRange $preset): self
    {
        return new self(DateRangeType::QUICK, quick: $preset);
    }

    public static function today(): self
    {
        return self::quick(QuickDateRange::TODAY);
    }

    public static function yesterday(): self
    {
        return self::quick(QuickDateRange::YESTERDAY);
    }

    public static function tomorrow(): self
    {
        return self::quick(QuickDateRange::TOMORROW);
    }

    public static function thisWeek(): self
    {
        return self::quick(QuickDateRange::THIS_WEEK);
    }

    public static function lastWeek(): self
    {
        return self::quick(QuickDateRange::LAST_WEEK);
    }

    public static function thisMonth(): self
    {
        return self::quick(QuickDateRange::THIS_MONTH);
    }

    public static function lastMonth(): self
    {
        return self::quick(QuickDateRange::LAST_MONTH);
    }

    public static function thisQuarter(): self
    {
        return self::quick(QuickDateRange::THIS_QUARTER);
    }

    public static function lastQuarter(): self
    {
        return self::quick(QuickDateRange::LAST_QUARTER);
    }

    public static function thisYear(): self
    {
        return self::quick(QuickDateRange::THIS_YEAR);
    }

    public static function lastYear(): self
    {
        return self::quick(QuickDateRange::LAST_YEAR);
    }

    public static function yearToDate(): self
    {
        return self::quick(QuickDateRange::YEAR_TO_DATE);
    }

    // ================================================================
    // RELATIVE - Range over multiple units
    // "Last 2 months", "Next 3 weeks"
    // ================================================================

    /**
     * Last X units as a range.
     *
     * last(2, MONTH)                    → Oct + Nov 2024 (if today is Nov)
     * last(2, MONTH, includePartial: false) → Sep + Oct 2024
     * last(30, DAY)                     → Last 30 days including today
     */
    public static function last(int $amount, DateUnit $unit, bool $includePartial = true): self
    {
        return new self(
            type: DateRangeType::RELATIVE,
            amount: $amount,
            unit: $unit,
            direction: DateDirection::PAST,
            includePartial: $includePartial,
        );
    }

    /**
     * Next X units as a range.
     */
    public static function next(int $amount, DateUnit $unit, bool $includePartial = true): self
    {
        return new self(
            type: DateRangeType::RELATIVE,
            amount: $amount,
            unit: $unit,
            direction: DateDirection::FUTURE,
            includePartial: $includePartial,
        );
    }

    // Convenience methods
    public static function lastDays(int $days, bool $includeToday = true): self
    {
        return self::last($days, DateUnit::DAY, $includeToday);
    }

    public static function lastWeeks(int $weeks, bool $includeCurrentWeek = true): self
    {
        return self::last($weeks, DateUnit::WEEK, $includeCurrentWeek);
    }

    public static function lastMonths(int $months, bool $includeCurrentMonth = true): self
    {
        return self::last($months, DateUnit::MONTH, $includeCurrentMonth);
    }

    public static function lastQuarters(int $quarters, bool $includeCurrentQuarter = true): self
    {
        return self::last($quarters, DateUnit::QUARTER, $includeCurrentQuarter);
    }

    public static function lastYears(int $years, bool $includeCurrentYear = true): self
    {
        return self::last($years, DateUnit::YEAR, $includeCurrentYear);
    }

    public static function nextDays(int $days, bool $includeToday = true): self
    {
        return self::next($days, DateUnit::DAY, $includeToday);
    }

    public static function nextWeeks(int $weeks): self
    {
        return self::next($weeks, DateUnit::WEEK);
    }

    public static function nextMonths(int $months): self
    {
        return self::next($months, DateUnit::MONTH);
    }

    // ================================================================
    // SPECIFIC - Single unit with offset
    // "2 months ago", "January 3 years ago", "Q4 last year"
    // ================================================================

    /**
     * A single unit X periods ago.
     *
     * unitAgo(2, MONTH)   → "2 months ago" → Sep 2024 (if now Nov 2024)
     * unitAgo(1, QUARTER) → "1 quarter ago" → Q3 2024
     * unitAgo(3, WEEK)    → "3 weeks ago" → KW 45
     */
    public static function unitAgo(int $offset, DateUnit $unit): self
    {
        return new self(
            type: DateRangeType::SPECIFIC,
            unit: $unit,
            unitOffset: -abs($offset),
        );
    }

    /**
     * A single unit X periods ahead.
     *
     * unitAhead(2, MONTH) → "In 2 months" → Jan 2025
     */
    public static function unitAhead(int $offset, DateUnit $unit): self
    {
        return new self(
            type: DateRangeType::SPECIFIC,
            unit: $unit,
            unitOffset: abs($offset),
        );
    }

    /**
     * Specific month with year offset.
     *
     * month(1, yearOffset: -3)  → "January 3 years ago"
     * month(6, year: 2024)      → "June 2024"
     * month(12)                 → "December this year"
     */
    public static function month(int $month, ?int $year = null, ?int $yearOffset = null): self
    {
        return new self(
            type: DateRangeType::SPECIFIC,
            month: $month,
            year: $year,
            yearOffset: $year === null ? ($yearOffset ?? 0) : null,
        );
    }

    /**
     * Specific quarter with year offset.
     *
     * quarter(4, yearOffset: -1)  → "Q4 last year"
     * quarter(3, yearOffset: 2)   → "Q3 in 2 years"
     * quarter(1, year: 2025)      → "Q1 2025"
     */
    public static function quarter(int $quarter, ?int $year = null, ?int $yearOffset = null): self
    {
        return new self(
            type: DateRangeType::SPECIFIC,
            quarter: $quarter,
            year: $year,
            yearOffset: $year === null ? ($yearOffset ?? 0) : null,
        );
    }

    /**
     * Specific calendar week with year offset.
     *
     * week(15, yearOffset: -1) → "Week 15 last year"
     */
    public static function week(int $week, ?int $year = null, ?int $yearOffset = null): self
    {
        return new self(
            type: DateRangeType::SPECIFIC,
            week: $week,
            year: $year,
            yearOffset: $year === null ? ($yearOffset ?? 0) : null,
        );
    }

    /**
     * Specific year (absolute).
     */
    public static function year(int $year): self
    {
        return new self(
            type: DateRangeType::SPECIFIC,
            year: $year,
        );
    }

    // ================================================================
    // ANNUAL RANGE - Cross-year patterns
    // "Fiscal year July-June starting 3 years ago"
    // ================================================================

    /**
     * Annual range pattern with start year offset.
     *
     * annualRange(7, 1, 6, 30, startYearOffset: -3)
     * → July 1 to June 30, starting 3 years ago
     * → 2021-07-01 to 2022-06-30 (if now 2024)
     */
    public static function annualRange(
        int $startMonth,
        int $startDay,
        int $endMonth,
        int $endDay,
        int $startYearOffset = 0
    ): self {
        return new self(
            type: DateRangeType::ANNUAL_RANGE,
            startMonth: $startMonth,
            startDay: $startDay,
            endMonth: $endMonth,
            endDay: $endDay,
            startYearOffset: $startYearOffset,
        );
    }

    /**
     * Standard fiscal year (July - June).
     */
    public static function fiscalYear(int $startYearOffset = 0): self
    {
        return self::annualRange(7, 1, 6, 30, $startYearOffset);
    }

    /**
     * Academic year (September - August).
     */
    public static function academicYear(int $startYearOffset = 0): self
    {
        return self::annualRange(9, 1, 8, 31, $startYearOffset);
    }

    /**
     * Calendar year with offset.
     */
    public static function calendarYear(int $yearOffset = 0): self
    {
        return self::annualRange(1, 1, 12, 31, $yearOffset);
    }

    // ================================================================
    // CUSTOM - Absolute date range
    // ================================================================

    /**
     * Custom date range.
     */
    public static function between(string $start, string $end): self
    {
        return new self(
            type: DateRangeType::CUSTOM,
            startDate: $start,
            endDate: $end,
        );
    }

    /**
     * From date onwards (open end).
     */
    public static function from(string $start): self
    {
        return new self(
            type: DateRangeType::CUSTOM,
            startDate: $start,
            endDate: null,
        );
    }

    /**
     * Until date (open start).
     */
    public static function until(string $end): self
    {
        return new self(
            type: DateRangeType::CUSTOM,
            startDate: null,
            endDate: $end,
        );
    }

    /**
     * Older than X units (open start).
     * "Älter als 90 Tage" → ∞ bis vor 90 Tagen
     */
    public static function olderThan(int $amount, DateUnit $unit): self
    {
        return new self(
            type: DateRangeType::RELATIVE,
            amount: $amount,
            unit: $unit,
            direction: DateDirection::PAST,
            includePartial: false,
            // Special flag: open start
            startDate: null,
        );
    }

    /**
     * Newer than X units (open end).
     * "Jünger als 30 Tage" → vor 30 Tagen bis heute
     */
    public static function newerThan(int $amount, DateUnit $unit): self
    {
        return new self(
            type: DateRangeType::RELATIVE,
            amount: $amount,
            unit: $unit,
            direction: DateDirection::PAST,
            includePartial: true,
            // Marker for newerThan logic
            endDate: 'now',
        );
    }

    // ================================================================
    // HALF YEAR (H1/H2)
    // ================================================================

    /**
     * Half year with optional year offset.
     *
     * halfYear(1)              → H1 this year (Jan-Jun)
     * halfYear(2)              → H2 this year (Jul-Dec)
     * halfYear(1, yearOffset: -1) → H1 last year
     */
    public static function halfYear(int $half, ?int $year = null, ?int $yearOffset = null): self
    {
        if ($half < 1 || $half > 2) {
            throw new \InvalidArgumentException('Half must be 1 or 2');
        }

        return new self(
            type: DateRangeType::SPECIFIC,
            month: $half === 1 ? 1 : 7,  // Start month: Jan or Jul
            quarter: null,
            week: null,
            year: $year,
            yearOffset: $year === null ? ($yearOffset ?? 0) : null,
            // Use special marker for half-year
            startMonth: $half === 1 ? 1 : 7,
            endMonth: $half === 1 ? 6 : 12,
        );
    }

    // ================================================================
    // EXPRESSION - Power User Syntax
    // ================================================================

    /**
     * Date range from expression strings.
     * Uses Symfony ExpressionLanguage with custom date functions.
     *
     * expression('now - 30 days')           → Last 30 days to now
     * expression('startOfMonth()', 'now')   → This month so far
     * expression('startOfQuarter() - 1 year', 'endOfQuarter() - 1 year')
     *                                       → Same quarter last year
     * expression('first day of last month', 'last day of last month')
     *                                       → PHP DateTime natural language
     *
     * @param string $start Start date expression
     * @param string|null $end End date expression (null = open end)
     */
    public static function expression(string $start, ?string $end = null): self
    {
        return new self(
            type: DateRangeType::EXPRESSION,
            startDate: $start,
            endDate: $end,
        );
    }

    /**
     * Single expression that returns a range.
     *
     * rangeExpression('lastMonth()')        → Last month
     * rangeExpression('thisQuarter()')      → This quarter
     * rangeExpression('fiscalYear(-1)')     → Last fiscal year
     */
    public static function rangeExpression(string $expression): self
    {
        return new self(
            type: DateRangeType::EXPRESSION,
            startDate: $expression,
            // Marker for "single range expression"
            endDate: '__range__',
        );
    }

    // ================================================================
    // RESOLUTION
    // ================================================================

    public function resolve(?Carbon $reference = null): ResolvedDateRange
    {
        $ref = $reference ?? Carbon::today();

        return match($this->type) {
            DateRangeType::QUICK => $this->resolveQuick($ref),
            DateRangeType::RELATIVE => $this->resolveRelative($ref),
            DateRangeType::SPECIFIC => $this->resolveSpecific($ref),
            DateRangeType::ANNUAL_RANGE => $this->resolveAnnualRange($ref),
            DateRangeType::CUSTOM => $this->resolveCustom($ref),
            DateRangeType::EXPRESSION => $this->resolveExpression($ref),
        };
    }

    private function resolveQuick(Carbon $ref): ResolvedDateRange
    {
        return match($this->quick) {
            QuickDateRange::TODAY => new ResolvedDateRange(
                $ref->copy()->startOfDay(),
                $ref->copy()->endOfDay()
            ),
            QuickDateRange::YESTERDAY => new ResolvedDateRange(
                $ref->copy()->subDay()->startOfDay(),
                $ref->copy()->subDay()->endOfDay()
            ),
            QuickDateRange::TOMORROW => new ResolvedDateRange(
                $ref->copy()->addDay()->startOfDay(),
                $ref->copy()->addDay()->endOfDay()
            ),
            QuickDateRange::THIS_WEEK => new ResolvedDateRange(
                $ref->copy()->startOfWeek(),
                $ref->copy()->endOfWeek()
            ),
            QuickDateRange::LAST_WEEK => new ResolvedDateRange(
                $ref->copy()->subWeek()->startOfWeek(),
                $ref->copy()->subWeek()->endOfWeek()
            ),
            QuickDateRange::NEXT_WEEK => new ResolvedDateRange(
                $ref->copy()->addWeek()->startOfWeek(),
                $ref->copy()->addWeek()->endOfWeek()
            ),
            QuickDateRange::THIS_MONTH => new ResolvedDateRange(
                $ref->copy()->startOfMonth(),
                $ref->copy()->endOfMonth()
            ),
            QuickDateRange::LAST_MONTH => new ResolvedDateRange(
                $ref->copy()->subMonth()->startOfMonth(),
                $ref->copy()->subMonth()->endOfMonth()
            ),
            QuickDateRange::NEXT_MONTH => new ResolvedDateRange(
                $ref->copy()->addMonth()->startOfMonth(),
                $ref->copy()->addMonth()->endOfMonth()
            ),
            QuickDateRange::THIS_QUARTER => new ResolvedDateRange(
                $ref->copy()->startOfQuarter(),
                $ref->copy()->endOfQuarter()
            ),
            QuickDateRange::LAST_QUARTER => new ResolvedDateRange(
                $ref->copy()->subQuarter()->startOfQuarter(),
                $ref->copy()->subQuarter()->endOfQuarter()
            ),
            QuickDateRange::NEXT_QUARTER => new ResolvedDateRange(
                $ref->copy()->addQuarter()->startOfQuarter(),
                $ref->copy()->addQuarter()->endOfQuarter()
            ),
            QuickDateRange::THIS_YEAR => new ResolvedDateRange(
                $ref->copy()->startOfYear(),
                $ref->copy()->endOfYear()
            ),
            QuickDateRange::LAST_YEAR => new ResolvedDateRange(
                $ref->copy()->subYear()->startOfYear(),
                $ref->copy()->subYear()->endOfYear()
            ),
            QuickDateRange::NEXT_YEAR => new ResolvedDateRange(
                $ref->copy()->addYear()->startOfYear(),
                $ref->copy()->addYear()->endOfYear()
            ),
            QuickDateRange::YEAR_TO_DATE => new ResolvedDateRange(
                $ref->copy()->startOfYear(),
                $ref->copy()->endOfDay()
            ),
            QuickDateRange::LAST_12_MONTHS => new ResolvedDateRange(
                $ref->copy()->subMonths(12)->startOfMonth(),
                $ref->copy()->endOfMonth()
            ),
            QuickDateRange::THIS_HALF_YEAR => new ResolvedDateRange(
                $ref->month <= 6
                    ? $ref->copy()->startOfYear()
                    : $ref->copy()->setMonth(7)->startOfMonth(),
                $ref->month <= 6
                    ? $ref->copy()->setMonth(6)->endOfMonth()
                    : $ref->copy()->endOfYear()
            ),
            QuickDateRange::LAST_HALF_YEAR => new ResolvedDateRange(
                $ref->month <= 6
                    ? $ref->copy()->subYear()->setMonth(7)->startOfMonth()
                    : $ref->copy()->startOfYear(),
                $ref->month <= 6
                    ? $ref->copy()->subYear()->endOfYear()
                    : $ref->copy()->setMonth(6)->endOfMonth()
            ),
            QuickDateRange::H1_THIS_YEAR => new ResolvedDateRange(
                $ref->copy()->startOfYear(),
                $ref->copy()->setMonth(6)->endOfMonth()
            ),
            QuickDateRange::H2_THIS_YEAR => new ResolvedDateRange(
                $ref->copy()->setMonth(7)->startOfMonth(),
                $ref->copy()->endOfYear()
            ),
            QuickDateRange::H1_LAST_YEAR => new ResolvedDateRange(
                $ref->copy()->subYear()->startOfYear(),
                $ref->copy()->subYear()->setMonth(6)->endOfMonth()
            ),
            QuickDateRange::H2_LAST_YEAR => new ResolvedDateRange(
                $ref->copy()->subYear()->setMonth(7)->startOfMonth(),
                $ref->copy()->subYear()->endOfYear()
            ),
        };
    }

    private function resolveRelative(Carbon $ref): ResolvedDateRange
    {
        $isPast = $this->direction === DateDirection::PAST;

        // Handle "older than" (open start): ∞ to X days ago
        if ($this->startDate === null && $isPast && !$this->includePartial) {
            $boundary = $ref->copy()->sub($this->unit->value, $this->amount);
            return new ResolvedDateRange(null, $boundary->startOfDay()->subSecond());
        }

        // Handle "newer than" (closed range): X days ago to now
        if ($this->endDate === 'now' && $isPast) {
            $start = $ref->copy()->sub($this->unit->value, $this->amount)->startOfDay();
            $end = $ref->copy()->endOfDay();
            return new ResolvedDateRange($start, $end);
        }

        if ($this->includePartial) {
            // Include current unit (today, this week, etc.)
            if ($isPast) {
                $start = $ref->copy()->sub($this->unit->value, $this->amount - 1)->startOf($this->unit->value);
                $end = $ref->copy()->endOf($this->unit->value);
            } else {
                $start = $ref->copy()->startOf($this->unit->value);
                $end = $ref->copy()->add($this->unit->value, $this->amount - 1)->endOf($this->unit->value);
            }
        } else {
            // Complete units only (exclude current)
            if ($isPast) {
                $end = $ref->copy()->sub($this->unit->value, 1)->endOf($this->unit->value);
                $start = $ref->copy()->sub($this->unit->value, $this->amount)->startOf($this->unit->value);
            } else {
                $start = $ref->copy()->add($this->unit->value, 1)->startOf($this->unit->value);
                $end = $ref->copy()->add($this->unit->value, $this->amount)->endOf($this->unit->value);
            }
        }

        return new ResolvedDateRange($start, $end);
    }

    private function resolveSpecific(Carbon $ref): ResolvedDateRange
    {
        // Handle unitOffset (e.g., "2 months ago")
        if ($this->unitOffset !== null && $this->unit !== null) {
            $target = $ref->copy()->add($this->unit->value, $this->unitOffset);
            return new ResolvedDateRange(
                $target->copy()->startOf($this->unit->value),
                $target->copy()->endOf($this->unit->value)
            );
        }

        // Calculate target year
        $year = $this->year ?? ($ref->year + ($this->yearOffset ?? 0));

        // Handle half-year (startMonth and endMonth set, but not quarter)
        if ($this->startMonth !== null && $this->endMonth !== null && $this->quarter === null) {
            $start = Carbon::create($year, $this->startMonth, 1)->startOfDay();
            $end = Carbon::create($year, $this->endMonth, 1)->endOfMonth();
            return new ResolvedDateRange($start, $end);
        }

        if ($this->month !== null) {
            $start = Carbon::create($year, $this->month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
        } elseif ($this->quarter !== null) {
            $start = Carbon::create($year, (($this->quarter - 1) * 3) + 1, 1)->startOfDay();
            $end = $start->copy()->endOfQuarter();
        } elseif ($this->week !== null) {
            $start = Carbon::create($year, 1, 1)->setISODate($year, $this->week)->startOfWeek();
            $end = $start->copy()->endOfWeek();
        } else {
            // Entire year
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();
        }

        return new ResolvedDateRange($start, $end);
    }

    private function resolveAnnualRange(Carbon $ref): ResolvedDateRange
    {
        $startYear = $ref->year + ($this->startYearOffset ?? 0);

        $start = Carbon::create($startYear, $this->startMonth, $this->startDay)->startOfDay();

        // End in same or next year?
        $endYear = $startYear;
        if ($this->endMonth < $this->startMonth ||
            ($this->endMonth === $this->startMonth && $this->endDay < $this->startDay)) {
            $endYear++;
        }

        $end = Carbon::create($endYear, $this->endMonth, $this->endDay)->endOfDay();

        return new ResolvedDateRange($start, $end);
    }

    private function resolveCustom(Carbon $ref): ResolvedDateRange
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : null;

        return new ResolvedDateRange($start, $end);
    }

    private function resolveExpression(Carbon $ref): ResolvedDateRange
    {
        $evaluator = new DateExpressionEvaluator($ref);

        // Single range expression (e.g., "lastMonth()")
        if ($this->endDate === '__range__') {
            return $evaluator->evaluateRangeExpression($this->startDate);
        }

        // Start/End expressions
        $start = $this->startDate !== null
            ? $evaluator->evaluateToCarbon($this->startDate)
            : null;

        $end = $this->endDate !== null
            ? $evaluator->evaluateToCarbon($this->endDate)
            : null;

        return new ResolvedDateRange(
            $start?->startOfDay(),
            $end?->endOfDay()
        );
    }

    // ================================================================
    // SERIALIZATION
    // ================================================================

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'quick' => $this->quick?->value,
            'amount' => $this->amount,
            'unit' => $this->unit?->value,
            'direction' => $this->direction?->value,
            'include_partial' => $this->includePartial === true ? null : false,
            'month' => $this->month,
            'quarter' => $this->quarter,
            'week' => $this->week,
            'year' => $this->year,
            'year_offset' => $this->yearOffset,
            'unit_offset' => $this->unitOffset,
            'start_month' => $this->startMonth,
            'start_day' => $this->startDay,
            'end_month' => $this->endMonth,
            'end_day' => $this->endDay,
            'start_year_offset' => $this->startYearOffset,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ], fn($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: DateRangeType::from($data['type']),
            quick: isset($data['quick']) ? QuickDateRange::from($data['quick']) : null,
            amount: $data['amount'] ?? null,
            unit: isset($data['unit']) ? DateUnit::from($data['unit']) : null,
            direction: isset($data['direction']) ? DateDirection::from($data['direction']) : null,
            includePartial: $data['include_partial'] ?? true,
            month: $data['month'] ?? null,
            quarter: $data['quarter'] ?? null,
            week: $data['week'] ?? null,
            year: $data['year'] ?? null,
            yearOffset: $data['year_offset'] ?? null,
            unitOffset: $data['unit_offset'] ?? null,
            startMonth: $data['start_month'] ?? null,
            startDay: $data['start_day'] ?? null,
            endMonth: $data['end_month'] ?? null,
            endDay: $data['end_day'] ?? null,
            startYearOffset: $data['start_year_offset'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true));
    }
}
```

### 3. ResolvedDateRange

```php
<?php

namespace Ameax\FilterCore\DateRange;

use Carbon\Carbon;

/**
 * Resolved date range with actual Carbon dates.
 */
class ResolvedDateRange
{
    public function __construct(
        public readonly ?Carbon $start,  // null = open (unbounded past)
        public readonly ?Carbon $end,    // null = open (unbounded future)
    ) {}

    public function isOpenStart(): bool
    {
        return $this->start === null;
    }

    public function isOpenEnd(): bool
    {
        return $this->end === null;
    }

    public function isSingleDay(): bool
    {
        return $this->start !== null
            && $this->end !== null
            && $this->start->isSameDay($this->end);
    }

    public function contains(Carbon $date): bool
    {
        if ($this->start !== null && $date->lt($this->start)) {
            return false;
        }
        if ($this->end !== null && $date->gt($this->end)) {
            return false;
        }
        return true;
    }

    public function toDateStrings(): array
    {
        return [
            'start' => $this->start?->toDateString(),
            'end' => $this->end?->toDateString(),
        ];
    }

    public function format(string $format = 'd.m.Y'): string
    {
        if ($this->isSingleDay()) {
            return $this->start->format($format);
        }

        $startStr = $this->start?->format($format) ?? '∞';
        $endStr = $this->end?->format($format) ?? '∞';

        return "{$startStr} – {$endStr}";
    }
}
```

### 4. DateExpressionEvaluator (Power User Feature)

```php
<?php

namespace Ameax\FilterCore\DateRange;

use Carbon\Carbon;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Evaluates date expressions using Symfony ExpressionLanguage.
 *
 * Provides a safe, sandboxed way to evaluate date expressions
 * for power users and configuration files.
 *
 * @example
 * $evaluator = new DateExpressionEvaluator();
 * $date = $evaluator->evaluateToCarbon('now - 30 days');
 * $range = $evaluator->evaluateRangeExpression('lastMonth()');
 */
class DateExpressionEvaluator
{
    private ExpressionLanguage $language;
    private Carbon $reference;

    public function __construct(?Carbon $reference = null)
    {
        $this->reference = $reference ?? Carbon::today();
        $this->language = new ExpressionLanguage();
        $this->registerFunctions();
    }

    /**
     * Register all date functions.
     */
    private function registerFunctions(): void
    {
        // ================================================================
        // BASIC DATE FUNCTIONS
        // ================================================================

        // now() - Current datetime
        $this->language->register('now',
            fn() => 'Carbon::now()',
            fn($args) => $this->reference->copy()
        );

        // today() - Today at start of day
        $this->language->register('today',
            fn() => 'Carbon::today()',
            fn($args) => $this->reference->copy()->startOfDay()
        );

        // date(string) - Parse date string
        $this->language->register('date',
            fn($date) => sprintf('Carbon::parse(%s)', $date),
            fn($args, $date) => Carbon::parse($date)
        );

        // ================================================================
        // START OF PERIOD FUNCTIONS
        // ================================================================

        $this->language->register('startOfDay',
            fn() => '$ref->copy()->startOfDay()',
            fn($args) => $this->reference->copy()->startOfDay()
        );

        $this->language->register('startOfWeek',
            fn() => '$ref->copy()->startOfWeek()',
            fn($args) => $this->reference->copy()->startOfWeek()
        );

        $this->language->register('startOfMonth',
            fn() => '$ref->copy()->startOfMonth()',
            fn($args) => $this->reference->copy()->startOfMonth()
        );

        $this->language->register('startOfQuarter',
            fn() => '$ref->copy()->startOfQuarter()',
            fn($args) => $this->reference->copy()->startOfQuarter()
        );

        $this->language->register('startOfYear',
            fn() => '$ref->copy()->startOfYear()',
            fn($args) => $this->reference->copy()->startOfYear()
        );

        // ================================================================
        // END OF PERIOD FUNCTIONS
        // ================================================================

        $this->language->register('endOfDay',
            fn() => '$ref->copy()->endOfDay()',
            fn($args) => $this->reference->copy()->endOfDay()
        );

        $this->language->register('endOfWeek',
            fn() => '$ref->copy()->endOfWeek()',
            fn($args) => $this->reference->copy()->endOfWeek()
        );

        $this->language->register('endOfMonth',
            fn() => '$ref->copy()->endOfMonth()',
            fn($args) => $this->reference->copy()->endOfMonth()
        );

        $this->language->register('endOfQuarter',
            fn() => '$ref->copy()->endOfQuarter()',
            fn($args) => $this->reference->copy()->endOfQuarter()
        );

        $this->language->register('endOfYear',
            fn() => '$ref->copy()->endOfYear()',
            fn($args) => $this->reference->copy()->endOfYear()
        );

        // ================================================================
        // RANGE FUNCTIONS (return ResolvedDateRange)
        // ================================================================

        $this->language->register('lastMonth',
            fn() => 'DateRangeValue::lastMonth()->resolve()',
            fn($args) => DateRangeValue::lastMonth()->resolve($this->reference)
        );

        $this->language->register('thisMonth',
            fn() => 'DateRangeValue::thisMonth()->resolve()',
            fn($args) => DateRangeValue::thisMonth()->resolve($this->reference)
        );

        $this->language->register('lastQuarter',
            fn() => 'DateRangeValue::lastQuarter()->resolve()',
            fn($args) => DateRangeValue::lastQuarter()->resolve($this->reference)
        );

        $this->language->register('thisQuarter',
            fn() => 'DateRangeValue::thisQuarter()->resolve()',
            fn($args) => DateRangeValue::thisQuarter()->resolve($this->reference)
        );

        $this->language->register('lastYear',
            fn() => 'DateRangeValue::lastYear()->resolve()',
            fn($args) => DateRangeValue::lastYear()->resolve($this->reference)
        );

        $this->language->register('thisYear',
            fn() => 'DateRangeValue::thisYear()->resolve()',
            fn($args) => DateRangeValue::thisYear()->resolve($this->reference)
        );

        // lastDays(n) - Last n days
        $this->language->register('lastDays',
            fn($n) => sprintf('DateRangeValue::lastDays(%d)->resolve()', $n),
            fn($args, $n) => DateRangeValue::lastDays($n)->resolve($this->reference)
        );

        // lastMonths(n) - Last n months
        $this->language->register('lastMonths',
            fn($n) => sprintf('DateRangeValue::lastMonths(%d)->resolve()', $n),
            fn($args, $n) => DateRangeValue::lastMonths($n)->resolve($this->reference)
        );

        // fiscalYear(offset) - Fiscal year with offset
        $this->language->register('fiscalYear',
            fn($offset = 0) => sprintf('DateRangeValue::fiscalYear(%d)->resolve()', $offset),
            fn($args, $offset = 0) => DateRangeValue::fiscalYear($offset)->resolve($this->reference)
        );

        // halfYear(half, yearOffset) - Half year
        $this->language->register('halfYear',
            fn($half, $yearOffset = 0) => sprintf('DateRangeValue::halfYear(%d, yearOffset: %d)->resolve()', $half, $yearOffset),
            fn($args, $half, $yearOffset = 0) => DateRangeValue::halfYear($half, yearOffset: $yearOffset)->resolve($this->reference)
        );

        // quarter(q, yearOffset) - Specific quarter
        $this->language->register('quarter',
            fn($q, $yearOffset = 0) => sprintf('DateRangeValue::quarter(%d, yearOffset: %d)->resolve()', $q, $yearOffset),
            fn($args, $q, $yearOffset = 0) => DateRangeValue::quarter($q, yearOffset: $yearOffset)->resolve($this->reference)
        );

        // month(m, yearOffset) - Specific month
        $this->language->register('month',
            fn($m, $yearOffset = 0) => sprintf('DateRangeValue::month(%d, yearOffset: %d)->resolve()', $m, $yearOffset),
            fn($args, $m, $yearOffset = 0) => DateRangeValue::month($m, yearOffset: $yearOffset)->resolve($this->reference)
        );

        // ================================================================
        // ARITHMETIC OPERATORS (via custom functions)
        // ================================================================

        // sub(date, amount, unit) - Subtract from date
        $this->language->register('sub',
            fn($date, $amount, $unit) => sprintf('(%s)->sub(%s, %d)', $date, $unit, $amount),
            fn($args, $date, $amount, $unit) => $date->copy()->sub($unit, $amount)
        );

        // add(date, amount, unit) - Add to date
        $this->language->register('add',
            fn($date, $amount, $unit) => sprintf('(%s)->add(%s, %d)', $date, $unit, $amount),
            fn($args, $date, $amount, $unit) => $date->copy()->add($unit, $amount)
        );
    }

    /**
     * Evaluate expression to Carbon date.
     */
    public function evaluateToCarbon(string $expression): Carbon
    {
        // Try PHP DateTime natural language first
        if ($this->isNaturalLanguage($expression)) {
            return Carbon::parse($expression);
        }

        $result = $this->language->evaluate($expression);

        if ($result instanceof Carbon) {
            return $result;
        }

        if ($result instanceof \DateTimeInterface) {
            return Carbon::instance($result);
        }

        throw new \InvalidArgumentException(
            "Expression '{$expression}' did not evaluate to a date"
        );
    }

    /**
     * Evaluate expression that returns a range.
     */
    public function evaluateRangeExpression(string $expression): ResolvedDateRange
    {
        $result = $this->language->evaluate($expression);

        if ($result instanceof ResolvedDateRange) {
            return $result;
        }

        throw new \InvalidArgumentException(
            "Expression '{$expression}' did not evaluate to a date range"
        );
    }

    /**
     * Check if expression is PHP DateTime natural language.
     */
    private function isNaturalLanguage(string $expression): bool
    {
        $patterns = [
            '/^(first|last|next|previous)\s+(day|week|month)/i',
            '/^(yesterday|today|tomorrow|now)$/i',
            '/^\d{4}-\d{2}-\d{2}/',  // ISO date
            '/^[+-]?\d+\s+(day|week|month|year)s?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available functions for documentation/UI.
     */
    public static function getAvailableFunctions(): array
    {
        return [
            'Date Points' => [
                'now()' => 'Current datetime',
                'today()' => 'Today at start of day',
                'date("Y-m-d")' => 'Parse date string',
            ],
            'Period Starts' => [
                'startOfDay()' => 'Start of today',
                'startOfWeek()' => 'Start of current week',
                'startOfMonth()' => 'Start of current month',
                'startOfQuarter()' => 'Start of current quarter',
                'startOfYear()' => 'Start of current year',
            ],
            'Period Ends' => [
                'endOfDay()' => 'End of today',
                'endOfWeek()' => 'End of current week',
                'endOfMonth()' => 'End of current month',
                'endOfQuarter()' => 'End of current quarter',
                'endOfYear()' => 'End of current year',
            ],
            'Ranges' => [
                'lastMonth()' => 'Previous month',
                'thisMonth()' => 'Current month',
                'lastQuarter()' => 'Previous quarter',
                'thisQuarter()' => 'Current quarter',
                'lastYear()' => 'Previous year',
                'thisYear()' => 'Current year',
                'lastDays(n)' => 'Last n days',
                'lastMonths(n)' => 'Last n months',
                'fiscalYear(offset)' => 'Fiscal year (Jul-Jun)',
                'halfYear(1|2, offset)' => 'Half year (H1/H2)',
                'quarter(1-4, offset)' => 'Specific quarter',
                'month(1-12, offset)' => 'Specific month',
            ],
            'Arithmetic' => [
                'sub(date, n, "days")' => 'Subtract from date',
                'add(date, n, "months")' => 'Add to date',
            ],
            'Natural Language' => [
                '"first day of last month"' => 'PHP DateTime syntax',
                '"last day of this month"' => 'PHP DateTime syntax',
                '"+30 days"' => 'Relative modifier',
                '"-1 year"' => 'Relative modifier',
            ],
        ];
    }
}
```

---

## DateFilter Implementation

### Base DateFilter Class

```php
<?php

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\EmptyMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\NotEmptyMatchMode;

abstract class DateFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATE;
    }

    /**
     * Allowed directions for this field.
     * Override in subclass to restrict.
     *
     * @return DateDirection[]
     */
    public function allowedDirections(): array
    {
        return [DateDirection::PAST, DateDirection::FUTURE];
    }

    /**
     * Whether "today" is available as option.
     */
    public function allowToday(): bool
    {
        return true;
    }

    public function defaultMode(): MatchModeContract
    {
        return new DateRangeMatchMode();
    }

    public function allowedModes(): array
    {
        $modes = [
            new DateRangeMatchMode(),
            new IsMatchMode(),  // Exact date
        ];

        if ($this->nullable()) {
            $modes[] = new EmptyMatchMode();
            $modes[] = new NotEmptyMatchMode();
        }

        return $modes;
    }

    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if ($value === null) {
            return null;
        }

        // Already a DateRangeValue
        if ($value instanceof DateRangeValue) {
            return $value;
        }

        // Array → DateRangeValue
        if (is_array($value) && isset($value['type'])) {
            return DateRangeValue::fromArray($value);
        }

        // ISO date string → single day DateRangeValue
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return DateRangeValue::between($value, $value);
        }

        // Carbon → single day
        if ($value instanceof \Carbon\Carbon) {
            $dateStr = $value->toDateString();
            return DateRangeValue::between($dateStr, $dateStr);
        }

        return $value;
    }
}
```

### DynamicDateFilter

```php
<?php

namespace Ameax\FilterCore\Filters\Dynamic;

use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\Filters\DateFilter;

/**
 * Dynamic date filter for runtime configuration.
 *
 * @example
 * $filter = DynamicDateFilter::dynamic('created_at')
 *     ->withColumn('created_at')
 *     ->withLabel('Created Date')
 *     ->withAllowedDirections([DateDirection::PAST])
 *     ->withNullable(true);
 */
class DynamicDateFilter extends DateFilter
{
    protected string $dynamicKey;
    protected string $dynamicColumn;
    protected ?string $dynamicLabel = null;
    protected ?string $dynamicRelation = null;
    protected bool $dynamicNullable = false;

    /** @var DateDirection[] */
    protected array $dynamicAllowedDirections = [DateDirection::PAST, DateDirection::FUTURE];
    protected bool $dynamicAllowToday = true;

    /** @var array<string, mixed> */
    protected array $dynamicMeta = [];

    public static function dynamic(string $key): static
    {
        $instance = new static();
        $instance->dynamicKey = $key;
        $instance->dynamicColumn = $key;

        return $instance;
    }

    public function withColumn(string $column): static
    {
        $this->dynamicColumn = $column;

        return $this;
    }

    public function withLabel(string $label): static
    {
        $this->dynamicLabel = $label;

        return $this;
    }

    public function withRelation(string $relation): static
    {
        $this->dynamicRelation = $relation;

        return $this;
    }

    public function withNullable(bool $nullable = true): static
    {
        $this->dynamicNullable = $nullable;

        return $this;
    }

    /**
     * @param DateDirection[] $directions
     */
    public function withAllowedDirections(array $directions): static
    {
        $this->dynamicAllowedDirections = $directions;

        return $this;
    }

    public function withPastOnly(): static
    {
        $this->dynamicAllowedDirections = [DateDirection::PAST];

        return $this;
    }

    public function withFutureOnly(): static
    {
        $this->dynamicAllowedDirections = [DateDirection::FUTURE];

        return $this;
    }

    public function withAllowToday(bool $allow = true): static
    {
        $this->dynamicAllowToday = $allow;

        return $this;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): static
    {
        $this->dynamicMeta = $meta;

        return $this;
    }

    // Override base methods

    public function key(): string
    {
        return $this->dynamicKey;
    }

    public function column(): string
    {
        return $this->dynamicColumn;
    }

    public function label(): ?string
    {
        return $this->dynamicLabel;
    }

    public function getRelation(): ?string
    {
        return $this->dynamicRelation;
    }

    public function nullable(): bool
    {
        return $this->dynamicNullable;
    }

    public function allowedDirections(): array
    {
        return $this->dynamicAllowedDirections;
    }

    public function allowToday(): bool
    {
        return $this->dynamicAllowToday;
    }

    public function getMeta(): array
    {
        return $this->dynamicMeta;
    }
}
```

**Usage Examples:**

```php
// Basic date filter (past and future)
$createdFilter = DynamicDateFilter::dynamic('created_at')
    ->withColumn('created_at')
    ->withLabel('Created Date');

// Past-only filter (e.g., for created_at - can't create in future)
$createdFilter = DynamicDateFilter::dynamic('created_at')
    ->withColumn('created_at')
    ->withLabel('Created Date')
    ->withPastOnly();

// Future-only filter (e.g., for due_date, scheduled_at)
$dueFilter = DynamicDateFilter::dynamic('due_date')
    ->withColumn('due_date')
    ->withLabel('Due Date')
    ->withFutureOnly()
    ->withNullable(true);

// Relation filter
$orderDateFilter = DynamicDateFilter::dynamic('order_created_at')
    ->withColumn('created_at')
    ->withRelation('orders')
    ->withLabel('Order Date')
    ->withPastOnly();
```

### DateRangeMatchMode

```php
<?php

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\ResolvedDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class DateRangeMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'date_range';
    }

    public function label(): string
    {
        return 'Zeitraum';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // Resolve to actual dates
        $resolved = $value instanceof DateRangeValue
            ? $value->resolve()
            : $value;

        if (!$resolved instanceof ResolvedDateRange) {
            return;
        }

        if ($resolved->start !== null) {
            $query->where($column, '>=', $resolved->start->toDateString());
        }

        if ($resolved->end !== null) {
            $query->where($column, '<=', $resolved->end->toDateString());
        }
    }

    public function appliesToCollection(mixed $itemValue, mixed $filterValue): bool
    {
        if ($itemValue === null) {
            return false;
        }

        $resolved = $filterValue instanceof DateRangeValue
            ? $filterValue->resolve()
            : $filterValue;

        if (!$resolved instanceof ResolvedDateRange) {
            return false;
        }

        $date = $itemValue instanceof \Carbon\Carbon
            ? $itemValue
            : \Carbon\Carbon::parse($itemValue);

        return $resolved->contains($date);
    }
}
```

### NotInDateRangeMatchMode (Inverse)

```php
<?php

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\ResolvedDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Matches records NOT in the specified date range.
 */
class NotInDateRangeMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'not_in_date_range';
    }

    public function label(): string
    {
        return 'Nicht im Zeitraum';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $resolved = $value instanceof DateRangeValue
            ? $value->resolve()
            : $value;

        if (!$resolved instanceof ResolvedDateRange) {
            return;
        }

        // NOT BETWEEN logic
        $query->where(function ($q) use ($column, $resolved) {
            if ($resolved->start !== null && $resolved->end !== null) {
                // Both bounds: NOT BETWEEN
                $q->where($column, '<', $resolved->start->toDateString())
                  ->orWhere($column, '>', $resolved->end->toDateString());
            } elseif ($resolved->start !== null) {
                // Only start: before start date
                $q->where($column, '<', $resolved->start->toDateString());
            } elseif ($resolved->end !== null) {
                // Only end: after end date
                $q->where($column, '>', $resolved->end->toDateString());
            }
        });
    }

    public function appliesToCollection(mixed $itemValue, mixed $filterValue): bool
    {
        if ($itemValue === null) {
            return true; // NULL is "not in range"
        }

        $resolved = $filterValue instanceof DateRangeValue
            ? $filterValue->resolve()
            : $filterValue;

        if (!$resolved instanceof ResolvedDateRange) {
            return false;
        }

        $date = $itemValue instanceof \Carbon\Carbon
            ? $itemValue
            : \Carbon\Carbon::parse($itemValue);

        return !$resolved->contains($date);
    }
}
```

---

## UI Configuration

### DateRangeOptions Helper

```php
<?php

namespace Ameax\FilterCore\DateRange;

class DateRangeOptions
{
    /**
     * Get available quick options based on allowed directions.
     */
    public static function getQuickOptions(array $allowedDirections, bool $allowToday = true): array
    {
        $options = [];
        $hasPast = in_array(DateDirection::PAST, $allowedDirections);
        $hasFuture = in_array(DateDirection::FUTURE, $allowedDirections);

        // Today
        if ($allowToday && ($hasPast || $hasFuture)) {
            $options[QuickDateRange::TODAY->value] = QuickDateRange::TODAY->label();
        }

        // Past options
        if ($hasPast) {
            $options[QuickDateRange::YESTERDAY->value] = QuickDateRange::YESTERDAY->label();
            $options[QuickDateRange::THIS_WEEK->value] = QuickDateRange::THIS_WEEK->label();
            $options[QuickDateRange::LAST_WEEK->value] = QuickDateRange::LAST_WEEK->label();
            $options[QuickDateRange::THIS_MONTH->value] = QuickDateRange::THIS_MONTH->label();
            $options[QuickDateRange::LAST_MONTH->value] = QuickDateRange::LAST_MONTH->label();
            $options[QuickDateRange::THIS_QUARTER->value] = QuickDateRange::THIS_QUARTER->label();
            $options[QuickDateRange::LAST_QUARTER->value] = QuickDateRange::LAST_QUARTER->label();
            $options[QuickDateRange::THIS_HALF_YEAR->value] = QuickDateRange::THIS_HALF_YEAR->label();
            $options[QuickDateRange::LAST_HALF_YEAR->value] = QuickDateRange::LAST_HALF_YEAR->label();
            $options[QuickDateRange::H1_THIS_YEAR->value] = QuickDateRange::H1_THIS_YEAR->label();
            $options[QuickDateRange::H2_THIS_YEAR->value] = QuickDateRange::H2_THIS_YEAR->label();
            $options[QuickDateRange::H1_LAST_YEAR->value] = QuickDateRange::H1_LAST_YEAR->label();
            $options[QuickDateRange::H2_LAST_YEAR->value] = QuickDateRange::H2_LAST_YEAR->label();
            $options[QuickDateRange::THIS_YEAR->value] = QuickDateRange::THIS_YEAR->label();
            $options[QuickDateRange::LAST_YEAR->value] = QuickDateRange::LAST_YEAR->label();
            $options[QuickDateRange::YEAR_TO_DATE->value] = QuickDateRange::YEAR_TO_DATE->label();
            $options[QuickDateRange::LAST_12_MONTHS->value] = QuickDateRange::LAST_12_MONTHS->label();
        }

        // Future options
        if ($hasFuture) {
            $options[QuickDateRange::TOMORROW->value] = QuickDateRange::TOMORROW->label();
            $options[QuickDateRange::NEXT_WEEK->value] = QuickDateRange::NEXT_WEEK->label();
            $options[QuickDateRange::NEXT_MONTH->value] = QuickDateRange::NEXT_MONTH->label();
            $options[QuickDateRange::NEXT_QUARTER->value] = QuickDateRange::NEXT_QUARTER->label();
            $options[QuickDateRange::NEXT_YEAR->value] = QuickDateRange::NEXT_YEAR->label();
        }

        return $options;
    }

    /**
     * Get relative presets.
     */
    public static function getRelativePresets(array $allowedDirections): array
    {
        $presets = [];

        if (in_array(DateDirection::PAST, $allowedDirections)) {
            $presets['last_7_days'] = ['label' => 'Letzte 7 Tage', 'amount' => 7, 'unit' => 'day'];
            $presets['last_14_days'] = ['label' => 'Letzte 14 Tage', 'amount' => 14, 'unit' => 'day'];
            $presets['last_30_days'] = ['label' => 'Letzte 30 Tage', 'amount' => 30, 'unit' => 'day'];
            $presets['last_90_days'] = ['label' => 'Letzte 90 Tage', 'amount' => 90, 'unit' => 'day'];
            $presets['last_6_weeks'] = ['label' => 'Letzte 6 Wochen', 'amount' => 6, 'unit' => 'week'];
            $presets['last_3_months'] = ['label' => 'Letzte 3 Monate', 'amount' => 3, 'unit' => 'month'];
            $presets['last_6_months'] = ['label' => 'Letzte 6 Monate', 'amount' => 6, 'unit' => 'month'];
            $presets['last_12_months'] = ['label' => 'Letzte 12 Monate', 'amount' => 12, 'unit' => 'month'];
        }

        if (in_array(DateDirection::FUTURE, $allowedDirections)) {
            $presets['next_7_days'] = ['label' => 'Nächste 7 Tage', 'amount' => 7, 'unit' => 'day'];
            $presets['next_14_days'] = ['label' => 'Nächste 14 Tage', 'amount' => 14, 'unit' => 'day'];
            $presets['next_30_days'] = ['label' => 'Nächste 30 Tage', 'amount' => 30, 'unit' => 'day'];
            $presets['next_90_days'] = ['label' => 'Nächste 90 Tage', 'amount' => 90, 'unit' => 'day'];
            $presets['next_3_months'] = ['label' => 'Nächste 3 Monate', 'amount' => 3, 'unit' => 'month'];
            $presets['next_6_months'] = ['label' => 'Nächste 6 Monate', 'amount' => 6, 'unit' => 'month'];
        }

        return $presets;
    }

    /**
     * Get year offset options.
     */
    public static function getYearOffsetOptions(array $allowedDirections, int $pastYears = 5, int $futureYears = 3): array
    {
        $options = [0 => 'Dieses Jahr'];

        if (in_array(DateDirection::PAST, $allowedDirections)) {
            for ($i = 1; $i <= $pastYears; $i++) {
                $options[-$i] = $i === 1 ? 'Letztes Jahr' : "Vor {$i} Jahren";
            }
        }

        if (in_array(DateDirection::FUTURE, $allowedDirections)) {
            for ($i = 1; $i <= $futureYears; $i++) {
                $options[$i] = $i === 1 ? 'Nächstes Jahr' : "In {$i} Jahren";
            }
        }

        return $options;
    }

    /**
     * Get month options.
     */
    public static function getMonthOptions(): array
    {
        return [
            1 => 'Januar', 2 => 'Februar', 3 => 'März',
            4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
    }

    /**
     * Get quarter options.
     */
    public static function getQuarterOptions(): array
    {
        return [
            1 => 'Q1 (Jan-Mär)',
            2 => 'Q2 (Apr-Jun)',
            3 => 'Q3 (Jul-Sep)',
            4 => 'Q4 (Okt-Dez)',
        ];
    }
}
```

---

## Usage Examples

### Code Examples

```php
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\DateUnit;

// ═══════════════════════════════════════════════════════════
// QUICK SELECTION
// ═══════════════════════════════════════════════════════════

DateRangeValue::today();           // Today
DateRangeValue::yesterday();       // Yesterday
DateRangeValue::lastWeek();        // Last week (Mon-Sun)
DateRangeValue::lastMonth();       // Last month
DateRangeValue::lastQuarter();     // Last quarter
DateRangeValue::lastYear();        // Last year
DateRangeValue::yearToDate();      // Jan 1 to today

// ═══════════════════════════════════════════════════════════
// RELATIVE - RANGE (Multiple Units)
// ═══════════════════════════════════════════════════════════

// "Last 30 days" (including today)
DateRangeValue::lastDays(30);
// → Nov 1 - Nov 30 (if today is Nov 30)

// "Last 2 months" (including current month)
DateRangeValue::lastMonths(2);
// → Oct 1 - Nov 30 (if today is Nov 30)

// "Last 2 months" (complete months only, excluding current)
DateRangeValue::lastMonths(2, includeCurrentMonth: false);
// → Sep 1 - Oct 31 (if today is Nov 30)

// "Last 6 weeks" (complete weeks only)
DateRangeValue::lastWeeks(6, includeCurrentWeek: false);
// → Oct 14 - Nov 24 (6 complete Mon-Sun weeks)

// "Next 3 months"
DateRangeValue::nextMonths(3);
// → Nov 1 - Jan 31 (if today is Nov 30)

// ═══════════════════════════════════════════════════════════
// SPECIFIC - SINGLE UNIT WITH OFFSET
// ═══════════════════════════════════════════════════════════

// "2 months ago" (single month)
DateRangeValue::unitAgo(2, DateUnit::MONTH);
// → Sep 1 - Sep 30 (if today is Nov 30)

// "3 weeks ago" (single week)
DateRangeValue::unitAgo(3, DateUnit::WEEK);
// → Nov 4 - Nov 10 (KW 45)

// "In 2 months" (single month)
DateRangeValue::unitAhead(2, DateUnit::MONTH);
// → Jan 1 - Jan 31

// "January 3 years ago"
DateRangeValue::month(1, yearOffset: -3);
// → Jan 1, 2021 - Jan 31, 2021 (if today is 2024)

// "Q4 last year"
DateRangeValue::quarter(4, yearOffset: -1);
// → Oct 1, 2023 - Dec 31, 2023

// "Q3 in 2 years"
DateRangeValue::quarter(3, yearOffset: 2);
// → Jul 1, 2026 - Sep 30, 2026

// "Week 15 last year"
DateRangeValue::week(15, yearOffset: -1);
// → Apr 10, 2023 - Apr 16, 2023

// "June 2024" (absolute)
DateRangeValue::month(6, year: 2024);
// → Jun 1, 2024 - Jun 30, 2024

// ═══════════════════════════════════════════════════════════
// ANNUAL RANGE - Cross-Year Patterns
// ═══════════════════════════════════════════════════════════

// "Fiscal year (Jul-Jun) starting 3 years ago"
DateRangeValue::fiscalYear(startYearOffset: -3);
// → Jul 1, 2021 - Jun 30, 2022 (if today is 2024)

// "Academic year (Sep-Aug) last year"
DateRangeValue::academicYear(startYearOffset: -1);
// → Sep 1, 2023 - Aug 31, 2024

// Custom: "Apr 1 to Mar 31, starting 2 years ago"
DateRangeValue::annualRange(4, 1, 3, 31, startYearOffset: -2);
// → Apr 1, 2022 - Mar 31, 2023

// ═══════════════════════════════════════════════════════════
// CUSTOM - Absolute Dates
// ═══════════════════════════════════════════════════════════

// Specific range
DateRangeValue::between('2024-01-01', '2024-03-31');

// From date onwards (open end)
DateRangeValue::from('2024-06-01');
// → Jun 1, 2024 - ∞

// Until date (open start)
DateRangeValue::until('2024-12-31');
// → ∞ - Dec 31, 2024

// Older than 90 days
DateRangeValue::olderThan(90, DateUnit::DAY);
// → ∞ - 90 days ago

// Newer than 30 days (within last 30 days)
DateRangeValue::newerThan(30, DateUnit::DAY);
// → 30 days ago - today

// ═══════════════════════════════════════════════════════════
// HALF YEAR (H1/H2)
// ═══════════════════════════════════════════════════════════

// H1 this year (Jan-Jun)
DateRangeValue::halfYear(1);
// → Jan 1 - Jun 30, 2024

// H2 last year (Jul-Dec)
DateRangeValue::halfYear(2, yearOffset: -1);
// → Jul 1 - Dec 31, 2023

// H1 in 2 years
DateRangeValue::halfYear(1, yearOffset: 2);
// → Jan 1 - Jun 30, 2026

// ═══════════════════════════════════════════════════════════
// EXPRESSION (Power Users)
// ═══════════════════════════════════════════════════════════

// Simple date expression
DateRangeValue::expression('first day of last month', 'last day of last month');
// → Oct 1 - Oct 31 (PHP DateTime natural language)

// Using functions
DateRangeValue::expression('startOfMonth()', 'now()');
// → Nov 1 - Nov 30 (this month so far)

// Arithmetic with sub/add
DateRangeValue::expression(
    'sub(startOfQuarter(), 1, "year")',
    'sub(endOfQuarter(), 1, "year")'
);
// → Q4 last year (same quarter, 1 year ago)

// Range expression (single expression returns full range)
DateRangeValue::rangeExpression('lastMonth()');
// → Oct 1 - Oct 31

DateRangeValue::rangeExpression('fiscalYear(-1)');
// → Jul 1, 2023 - Jun 30, 2024

DateRangeValue::rangeExpression('quarter(3, -2)');
// → Q3 two years ago

DateRangeValue::rangeExpression('halfYear(1, -1)');
// → H1 last year (Jan-Jun 2023)
```

### Filter Usage

```php
use App\Filters\CreatedAtFilter;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;

// Quick selection - Last month
$results = QueryApplicator::for(User::query())
    ->withFilters([CreatedAtFilter::class])
    ->applyFilter(new FilterValue(
        'created_at',
        new DateRangeMatchMode(),
        DateRangeValue::lastMonth()
    ))
    ->getQuery()
    ->get();

// Last 30 days
$results = QueryApplicator::for(User::query())
    ->withFilters([CreatedAtFilter::class])
    ->applyFilter(new FilterValue(
        'created_at',
        new DateRangeMatchMode(),
        DateRangeValue::lastDays(30)
    ))
    ->getQuery()
    ->get();

// Q4 last year
$results = QueryApplicator::for(User::query())
    ->withFilters([CreatedAtFilter::class])
    ->applyFilter(new FilterValue(
        'created_at',
        new DateRangeMatchMode(),
        DateRangeValue::quarter(4, yearOffset: -1)
    ))
    ->getQuery()
    ->get();

// Fiscal year 3 years ago
$results = QueryApplicator::for(Invoice::query())
    ->withFilters([InvoiceDateFilter::class])
    ->applyFilter(new FilterValue(
        'invoice_date',
        new DateRangeMatchMode(),
        DateRangeValue::fiscalYear(startYearOffset: -3)
    ))
    ->getQuery()
    ->get();

// H1 last year
$results = QueryApplicator::for(Report::query())
    ->withFilters([ReportDateFilter::class])
    ->applyFilter(new FilterValue(
        'report_date',
        new DateRangeMatchMode(),
        DateRangeValue::halfYear(1, yearOffset: -1)
    ))
    ->getQuery()
    ->get();

// NOT in Q4 (inverse filter)
$results = QueryApplicator::for(Order::query())
    ->withFilters([OrderDateFilter::class])
    ->applyFilter(new FilterValue(
        'order_date',
        new NotInDateRangeMatchMode(),
        DateRangeValue::lastQuarter()
    ))
    ->getQuery()
    ->get();
```

### JSON Serialization

```json
// Today
{"type": "quick", "quick": "today"}

// Last 30 days
{"type": "relative", "amount": 30, "unit": "day", "direction": "past"}

// Last 2 months (complete, excluding current)
{"type": "relative", "amount": 2, "unit": "month", "direction": "past", "include_partial": false}

// 2 months ago (single month)
{"type": "specific", "unit": "month", "unit_offset": -2}

// Q4 last year
{"type": "specific", "quarter": 4, "year_offset": -1}

// January 3 years ago
{"type": "specific", "month": 1, "year_offset": -3}

// Fiscal year starting 3 years ago
{"type": "annual_range", "start_month": 7, "start_day": 1, "end_month": 6, "end_day": 30, "start_year_offset": -3}

// Custom range
{"type": "custom", "start_date": "2024-01-01", "end_date": "2024-03-31"}

// Newer than 30 days
{"type": "relative", "amount": 30, "unit": "day", "direction": "past", "end_date": "now"}

// Half year H1 this year
{"type": "specific", "month": 1, "year_offset": 0, "start_month": 1, "end_month": 6}

// Half year H2 last year
{"type": "specific", "month": 7, "year_offset": -1, "start_month": 7, "end_month": 12}

// Expression - date range
{"type": "expression", "start_date": "first day of last month", "end_date": "last day of last month"}

// Expression - with functions
{"type": "expression", "start_date": "startOfMonth()", "end_date": "now()"}

// Expression - range function
{"type": "expression", "start_date": "lastMonth()", "end_date": "__range__"}

// Expression - fiscal year with offset
{"type": "expression", "start_date": "fiscalYear(-1)", "end_date": "__range__"}
```

---

## Implementation Steps

1. Create `DateRange` namespace with enums:
   - `DateRangeType`
   - `DateUnit`
   - `DateDirection`
   - `QuickDateRange` (incl. half-year presets)

2. Create `DateRangeValue` DTO with all factory methods:
   - Quick selections
   - Relative: `last()`, `next()`, `olderThan()`, `newerThan()`
   - Specific: `unitAgo()`, `month()`, `quarter()`, `week()`, `halfYear()`
   - Annual ranges: `fiscalYear()`, `academicYear()`, `annualRange()`
   - Custom: `between()`, `from()`, `until()`
   - Expression: `expression()`, `rangeExpression()`

3. Create `ResolvedDateRange` class

4. Create `DateExpressionEvaluator` (optional, requires `symfony/expression-language`):
   - Register date functions (now, today, startOf*, endOf*)
   - Register range functions (lastMonth, thisQuarter, etc.)
   - Support PHP DateTime natural language
   - Support arithmetic (sub, add)

5. Create `DateRangeOptions` helper for UI

6. Add `FilterTypeEnum::DATE` case

7. Create `DateFilter` base class with direction restrictions

8. Create `DynamicDateFilter`

9. Create match modes:
   - `DateRangeMatchMode`
   - `NotInDateRangeMatchMode`

10. Add comprehensive tests:
    - All quick selections (incl. half-years)
    - Relative ranges with/without current unit
    - newerThan / olderThan
    - Specific periods with year offset
    - Half-year (H1/H2)
    - Unit offset ("2 months ago" vs "last 2 months")
    - Annual ranges
    - Custom ranges (open start/end)
    - Not in date range (inverse)
    - Direction filtering
    - Expression evaluation (if enabled)
    - PHP DateTime natural language parsing
    - JSON serialization round-trip

11. Add to documentation

12. Optional: Add `symfony/expression-language` as suggested dependency

---

## Comparison Tables

### "Vor 2 Monaten" vs "Letzte 2 Monate"

| Expression | Method | Result (if today Nov 30, 2024) |
|------------|--------|--------------------------------|
| Vor 2 Monaten | `unitAgo(2, MONTH)` | Sep 1 - Sep 30 (1 month) |
| Letzte 2 Monate | `lastMonths(2)` | Oct 1 - Nov 30 (2 months) |
| Letzte 2 Monate (komplett) | `lastMonths(2, false)` | Sep 1 - Oct 31 (2 months) |

### Age-Based Filters

| Expression | Method | Result (if today Nov 30, 2024) |
|------------|--------|--------------------------------|
| Älter als 90 Tage | `olderThan(90, DAY)` | ∞ - Sep 1 |
| Jünger als 30 Tage | `newerThan(30, DAY)` | Nov 1 - Nov 30 |

### Half-Year (H1/H2)

| Expression | Method | Result (if today Nov 30, 2024) |
|------------|--------|--------------------------------|
| H1 dieses Jahr | `halfYear(1)` | Jan 1 - Jun 30, 2024 |
| H2 dieses Jahr | `halfYear(2)` | Jul 1 - Dec 31, 2024 |
| H1 letztes Jahr | `halfYear(1, yearOffset: -1)` | Jan 1 - Jun 30, 2023 |
| Dieses Halbjahr | Quick: `THIS_HALF_YEAR` | Jul 1 - Dec 31, 2024 |
| Letztes Halbjahr | Quick: `LAST_HALF_YEAR` | Jan 1 - Jun 30, 2024 |

### Inverse (Not In Range)

| Expression | Match Mode | SQL |
|------------|------------|-----|
| Nicht in Q4 | `NotInDateRangeMatchMode` | `WHERE date < '2024-10-01' OR date > '2024-12-31'` |

### Expression Syntax (Power Users)

| Expression | Equivalent | Result |
|------------|------------|--------|
| `"first day of last month"` | `lastMonth()` start | Oct 1 |
| `"last day of last month"` | `lastMonth()` end | Oct 31 |
| `startOfMonth()` | `thisMonth()` start | Nov 1 |
| `sub(now(), 30, "days")` | `lastDays(30)` start | Oct 31 |
| `lastMonth()` | `DateRangeValue::lastMonth()` | Oct 1-31 |
| `quarter(4, -1)` | `DateRangeValue::quarter(4, -1)` | Q4 2023 |
| `fiscalYear(-2)` | `DateRangeValue::fiscalYear(-2)` | Jul 2022 - Jun 2023 |

---

## Notes

- All relative dates resolve at query time based on current date
- `ResolvedDateRange` can have `null` start or end for open ranges
- Filter direction restrictions apply to UI options, not to the query itself
- Carbon is used internally for all date calculations
- JSON serialization allows storing filter presets in database
- UI components receive filtered options based on `allowedDirections()`
- Expression support requires `symfony/expression-language` package (optional)
- Expression syntax supports both custom functions and PHP DateTime natural language
- Expressions are evaluated in a sandboxed environment (no arbitrary code execution)

---

## Future Considerations

### Field-Relative Expressions (TODO)

Allow date calculations based on other date columns in the same record:

**Use Cases:**
- "3 Monate nach Vertragsbeginn" (3 months after contract_start)
- "Innerhalb von 30 Tagen nach Erstelldatum" (within 30 days of created_at)
- "Zwischen Einstellungs- und Kündigungsdatum" (between hire_date and termination_date)

**Possible Approaches:**

1. **Expression with Field References**
   ```php
   DateRangeValue::expression(
       'addMonths(field("contract_start"), 3)',
       'addMonths(field("contract_start"), 6)'
   )
   // → SQL: DATE_ADD(contract_start, INTERVAL 3 MONTH)
   ```

2. **Dedicated FIELD_RELATIVE Type**
   ```php
   DateRangeValue::relativeToField('contract_start')
       ->add(3, DateUnit::MONTH)
       ->until(6, DateUnit::MONTH);

   DateRangeValue::withinDaysOf('created_at', 30);
   ```

**Challenges:**
- Symfony ExpressionLanguage evaluates in PHP, but we need SQL
- SQL translation is database-specific (MySQL vs PostgreSQL vs SQLite)
- Security: avoid raw SQL injection

**Decision deferred** - evaluate based on actual use case requirements.
