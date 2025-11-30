<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Universal date range value object.
 *
 * Supports multiple types of date range definitions:
 * - Quick: Predefined ranges (today, last_week, this_month)
 * - Relative: Rolling ranges (last 30 days, next 2 weeks)
 * - Specific: Specific period with offset (January 2023, Q4 last year)
 * - Annual Range: Cross-year patterns (fiscal year starting July)
 * - Custom: User-defined start/end dates
 * - Expression: Power user syntax with ExpressionLanguage
 */
readonly class DateRangeValue
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function __construct(
        public DateRangeType $type,
        public array $config = [],
    ) {}

    // =========================================================================
    // QUICK SELECTIONS
    // =========================================================================

    /**
     * Create a quick date range selection.
     */
    public static function quick(QuickDateRange $range): self
    {
        return new self(DateRangeType::QUICK, [
            'quick' => $range->value,
        ]);
    }

    /**
     * Today.
     */
    public static function today(): self
    {
        return self::quick(QuickDateRange::TODAY);
    }

    /**
     * Yesterday.
     */
    public static function yesterday(): self
    {
        return self::quick(QuickDateRange::YESTERDAY);
    }

    /**
     * Tomorrow.
     */
    public static function tomorrow(): self
    {
        return self::quick(QuickDateRange::TOMORROW);
    }

    /**
     * This week.
     */
    public static function thisWeek(): self
    {
        return self::quick(QuickDateRange::THIS_WEEK);
    }

    /**
     * Last week.
     */
    public static function lastWeek(): self
    {
        return self::quick(QuickDateRange::LAST_WEEK);
    }

    /**
     * This month.
     */
    public static function thisMonth(): self
    {
        return self::quick(QuickDateRange::THIS_MONTH);
    }

    /**
     * Last month.
     */
    public static function lastMonth(): self
    {
        return self::quick(QuickDateRange::LAST_MONTH);
    }

    /**
     * This quarter.
     */
    public static function thisQuarter(): self
    {
        return self::quick(QuickDateRange::THIS_QUARTER);
    }

    /**
     * Last quarter.
     */
    public static function lastQuarter(): self
    {
        return self::quick(QuickDateRange::LAST_QUARTER);
    }

    /**
     * This year.
     */
    public static function thisYear(): self
    {
        return self::quick(QuickDateRange::THIS_YEAR);
    }

    /**
     * Last year.
     */
    public static function lastYear(): self
    {
        return self::quick(QuickDateRange::LAST_YEAR);
    }

    // =========================================================================
    // RELATIVE RANGES
    // =========================================================================

    /**
     * Last X units (rolling range including current partial unit).
     *
     * Example: lastDays(30) = last 30 days including today
     */
    public static function last(int $amount, DateUnit $unit, bool $includePartial = true): self
    {
        return new self(DateRangeType::RELATIVE, [
            'direction' => 'past',
            'amount' => $amount,
            'unit' => $unit->value,
            'includePartial' => $includePartial,
        ]);
    }

    /**
     * Last X days.
     */
    public static function lastDays(int $days, bool $includeToday = true): self
    {
        return self::last($days, DateUnit::DAY, $includeToday);
    }

    /**
     * Last X weeks.
     */
    public static function lastWeeks(int $weeks, bool $includeCurrentWeek = true): self
    {
        return self::last($weeks, DateUnit::WEEK, $includeCurrentWeek);
    }

    /**
     * Last X months.
     */
    public static function lastMonths(int $months, bool $includeCurrentMonth = true): self
    {
        return self::last($months, DateUnit::MONTH, $includeCurrentMonth);
    }

    /**
     * Last X quarters.
     */
    public static function lastQuarters(int $quarters, bool $includeCurrentQuarter = true): self
    {
        return self::last($quarters, DateUnit::QUARTER, $includeCurrentQuarter);
    }

    /**
     * Last X years.
     */
    public static function lastYears(int $years, bool $includeCurrentYear = true): self
    {
        return self::last($years, DateUnit::YEAR, $includeCurrentYear);
    }

    /**
     * Next X units (rolling range).
     */
    public static function next(int $amount, DateUnit $unit, bool $includePartial = true): self
    {
        return new self(DateRangeType::RELATIVE, [
            'direction' => 'future',
            'amount' => $amount,
            'unit' => $unit->value,
            'includePartial' => $includePartial,
        ]);
    }

    /**
     * Next X days.
     */
    public static function nextDays(int $days, bool $includeToday = true): self
    {
        return self::next($days, DateUnit::DAY, $includeToday);
    }

    /**
     * Next X weeks.
     */
    public static function nextWeeks(int $weeks, bool $includeCurrentWeek = true): self
    {
        return self::next($weeks, DateUnit::WEEK, $includeCurrentWeek);
    }

    /**
     * Next X months.
     */
    public static function nextMonths(int $months, bool $includeCurrentMonth = true): self
    {
        return self::next($months, DateUnit::MONTH, $includeCurrentMonth);
    }

    /**
     * Older than X units (open start, up to X ago).
     */
    public static function olderThan(int $amount, DateUnit $unit): self
    {
        return new self(DateRangeType::RELATIVE, [
            'direction' => 'past',
            'amount' => $amount,
            'unit' => $unit->value,
            'openStart' => true,
        ]);
    }

    /**
     * Newer than X units (from X ago to now).
     */
    public static function newerThan(int $amount, DateUnit $unit): self
    {
        return new self(DateRangeType::RELATIVE, [
            'direction' => 'past',
            'amount' => $amount,
            'unit' => $unit->value,
            'endDate' => 'now',
        ]);
    }

    // =========================================================================
    // SPECIFIC PERIODS
    // =========================================================================

    /**
     * Specific unit X periods ago.
     *
     * Example: unitAgo(2, MONTH) = the month 2 months ago (September if now is November)
     */
    public static function unitAgo(int $offset, DateUnit $unit): self
    {
        return new self(DateRangeType::SPECIFIC, [
            'unit' => $unit->value,
            'offset' => -abs($offset),
        ]);
    }

    /**
     * Specific month with year offset.
     *
     * @param  int  $month  Month number (1-12)
     * @param  int  $yearOffset  Year offset (0 = this year, -1 = last year)
     */
    public static function month(int $month, int $yearOffset = 0): self
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12');
        }

        return new self(DateRangeType::SPECIFIC, [
            'unit' => DateUnit::MONTH->value,
            'month' => $month,
            'yearOffset' => $yearOffset,
        ]);
    }

    /**
     * Specific quarter with year offset.
     *
     * @param  int  $quarter  Quarter number (1-4)
     * @param  int  $yearOffset  Year offset (0 = this year, -1 = last year)
     */
    public static function quarter(int $quarter, int $yearOffset = 0): self
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Quarter must be between 1 and 4');
        }

        return new self(DateRangeType::SPECIFIC, [
            'unit' => DateUnit::QUARTER->value,
            'quarter' => $quarter,
            'yearOffset' => $yearOffset,
        ]);
    }

    /**
     * Specific half year with year offset.
     *
     * @param  int  $half  Half number (1 = H1 Jan-Jun, 2 = H2 Jul-Dec)
     * @param  int  $yearOffset  Year offset (0 = this year, -1 = last year)
     */
    public static function halfYear(int $half, int $yearOffset = 0): self
    {
        if ($half < 1 || $half > 2) {
            throw new InvalidArgumentException('Half must be 1 (H1) or 2 (H2)');
        }

        $startMonth = $half === 1 ? 1 : 7;
        $endMonth = $half === 1 ? 6 : 12;

        return new self(DateRangeType::SPECIFIC, [
            'unit' => DateUnit::HALF_YEAR->value,
            'startMonth' => $startMonth,
            'endMonth' => $endMonth,
            'yearOffset' => $yearOffset,
        ]);
    }

    /**
     * Specific week with offset.
     *
     * @param  int  $weekNumber  ISO week number (1-53)
     * @param  int  $yearOffset  Year offset (0 = this year, -1 = last year)
     */
    public static function week(int $weekNumber, int $yearOffset = 0): self
    {
        if ($weekNumber < 1 || $weekNumber > 53) {
            throw new InvalidArgumentException('Week number must be between 1 and 53');
        }

        return new self(DateRangeType::SPECIFIC, [
            'unit' => DateUnit::WEEK->value,
            'week' => $weekNumber,
            'yearOffset' => $yearOffset,
        ]);
    }

    /**
     * Specific year.
     */
    public static function year(int $year): self
    {
        return new self(DateRangeType::SPECIFIC, [
            'unit' => DateUnit::YEAR->value,
            'year' => $year,
        ]);
    }

    // =========================================================================
    // ANNUAL RANGES
    // =========================================================================

    /**
     * Fiscal year starting in given month.
     *
     * @param  int  $startMonth  Month the fiscal year starts (1-12)
     * @param  int  $yearOffset  Year offset from current fiscal year
     */
    public static function fiscalYear(int $startMonth = 7, int $yearOffset = 0): self
    {
        return new self(DateRangeType::ANNUAL_RANGE, [
            'startMonth' => $startMonth,
            'yearOffset' => $yearOffset,
            'rangeType' => 'fiscal',
        ]);
    }

    /**
     * Academic year (typically September to August).
     *
     * @param  int  $startMonth  Month the academic year starts (default: 9)
     * @param  int  $yearOffset  Year offset from current academic year
     */
    public static function academicYear(int $startMonth = 9, int $yearOffset = 0): self
    {
        return new self(DateRangeType::ANNUAL_RANGE, [
            'startMonth' => $startMonth,
            'yearOffset' => $yearOffset,
            'rangeType' => 'academic',
        ]);
    }

    /**
     * Custom annual range starting in given month.
     *
     * @param  int  $startMonth  Month the annual range starts (1-12)
     * @param  int  $yearOffset  Year offset
     */
    public static function annualRange(int $startMonth, int $yearOffset = 0): self
    {
        return new self(DateRangeType::ANNUAL_RANGE, [
            'startMonth' => $startMonth,
            'yearOffset' => $yearOffset,
            'rangeType' => 'custom',
        ]);
    }

    // =========================================================================
    // CUSTOM RANGES
    // =========================================================================

    /**
     * Custom date range between two dates.
     */
    public static function between(Carbon|string $start, Carbon|string $end): self
    {
        $startDate = $start instanceof Carbon ? $start : Carbon::parse($start);
        $endDate = $end instanceof Carbon ? $end : Carbon::parse($end);

        return new self(DateRangeType::CUSTOM, [
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
        ]);
    }

    /**
     * From date to now/future (open end).
     */
    public static function from(Carbon|string $start): self
    {
        $startDate = $start instanceof Carbon ? $start : Carbon::parse($start);

        return new self(DateRangeType::CUSTOM, [
            'start' => $startDate->toDateString(),
            'end' => null,
        ]);
    }

    /**
     * From past to date (open start).
     */
    public static function until(Carbon|string $end): self
    {
        $endDate = $end instanceof Carbon ? $end : Carbon::parse($end);

        return new self(DateRangeType::CUSTOM, [
            'start' => null,
            'end' => $endDate->toDateString(),
        ]);
    }

    // =========================================================================
    // EXPRESSION (Power User)
    // =========================================================================

    /**
     * Create from expression string.
     *
     * Supports PHP DateTime natural language and custom functions.
     */
    public static function expression(string $expression): self
    {
        return new self(DateRangeType::EXPRESSION, [
            'expression' => $expression,
        ]);
    }

    /**
     * Create from two expression strings for start and end.
     */
    public static function rangeExpression(string $startExpression, string $endExpression): self
    {
        return new self(DateRangeType::EXPRESSION, [
            'startExpression' => $startExpression,
            'endExpression' => $endExpression,
        ]);
    }

    // =========================================================================
    // RESOLUTION
    // =========================================================================

    /**
     * Resolve this date range to concrete start/end dates.
     */
    public function resolve(?Carbon $reference = null): ResolvedDateRange
    {
        $ref = $reference ?? Carbon::now();

        return match ($this->type) {
            DateRangeType::QUICK => $this->resolveQuick($ref),
            DateRangeType::RELATIVE => $this->resolveRelative($ref),
            DateRangeType::SPECIFIC => $this->resolveSpecific($ref),
            DateRangeType::ANNUAL_RANGE => $this->resolveAnnualRange($ref),
            DateRangeType::CUSTOM => $this->resolveCustom(),
            DateRangeType::EXPRESSION => $this->resolveExpression($ref),
        };
    }

    /**
     * Resolve QUICK type.
     */
    protected function resolveQuick(Carbon $ref): ResolvedDateRange
    {
        $quickRange = QuickDateRange::from($this->config['quick']);
        $resolved = $quickRange->resolve($ref);

        return new ResolvedDateRange($resolved['start'], $resolved['end']);
    }

    /**
     * Resolve RELATIVE type.
     */
    protected function resolveRelative(Carbon $ref): ResolvedDateRange
    {
        $amount = $this->config['amount'];
        $unit = DateUnit::from($this->config['unit']);
        $isPast = ($this->config['direction'] ?? 'past') === 'past';
        $includePartial = $this->config['includePartial'] ?? true;
        $openStart = $this->config['openStart'] ?? false;
        $endDate = $this->config['endDate'] ?? null;

        // Handle "older than" (open start): ∞ to X units ago
        if ($openStart && $isPast) {
            $boundary = $this->subtractUnits($ref->copy(), $amount, $unit);

            return new ResolvedDateRange(null, $boundary->startOfDay()->subSecond());
        }

        // Handle "newer than" (X units ago to now)
        if ($endDate === 'now' && $isPast) {
            $start = $this->subtractUnits($ref->copy(), $amount, $unit)->startOfDay();

            return new ResolvedDateRange($start, $ref->copy()->endOfDay());
        }

        // Standard relative range
        if ($isPast) {
            if ($includePartial) {
                // Include current partial unit
                $start = $this->subtractUnits($ref->copy(), $amount - 1, $unit);
                $start = $this->startOfUnit($start, $unit);
                $end = $ref->copy()->endOfDay();
            } else {
                // Complete units only (excluding current)
                $end = $this->startOfUnit($ref->copy(), $unit)->subSecond();
                $start = $this->subtractUnits($ref->copy(), $amount, $unit);
                $start = $this->startOfUnit($start, $unit);
            }
        } else {
            // Future
            if ($includePartial) {
                $start = $ref->copy()->startOfDay();
                $end = $this->addUnits($ref->copy(), $amount - 1, $unit);
                $end = $this->endOfUnit($end, $unit);
            } else {
                $start = $this->endOfUnit($ref->copy(), $unit)->addSecond();
                $end = $this->addUnits($ref->copy(), $amount, $unit);
                $end = $this->endOfUnit($end, $unit);
            }
        }

        return new ResolvedDateRange($start, $end);
    }

    /**
     * Resolve SPECIFIC type.
     */
    protected function resolveSpecific(Carbon $ref): ResolvedDateRange
    {
        $unit = DateUnit::from($this->config['unit']);
        $yearOffset = $this->config['yearOffset'] ?? 0;
        $year = $ref->year + $yearOffset;

        // Handle half-year (startMonth and endMonth set, but not quarter)
        if (isset($this->config['startMonth'], $this->config['endMonth']) && ! isset($this->config['quarter'])) {
            /** @var Carbon $start */
            $start = Carbon::create($year, $this->config['startMonth'], 1);
            /** @var Carbon $end */
            $end = Carbon::create($year, $this->config['endMonth'], 1);

            return new ResolvedDateRange($start->startOfDay(), $end->endOfMonth());
        }

        // Handle specific month
        if (isset($this->config['month'])) {
            /** @var Carbon $start */
            $start = Carbon::create($year, $this->config['month'], 1);
            /** @var Carbon $end */
            $end = Carbon::create($year, $this->config['month'], 1);

            return new ResolvedDateRange($start->startOfMonth(), $end->endOfMonth());
        }

        // Handle specific quarter
        if (isset($this->config['quarter'])) {
            $quarterStartMonth = (($this->config['quarter'] - 1) * 3) + 1;
            /** @var Carbon $start */
            $start = Carbon::create($year, $quarterStartMonth, 1);
            $start = $start->startOfMonth();
            $end = $start->copy()->addMonths(2)->endOfMonth();

            return new ResolvedDateRange($start, $end);
        }

        // Handle specific week
        if (isset($this->config['week'])) {
            /** @var Carbon $start */
            $start = Carbon::create($year, 1, 1);
            $start = $start->setISODate($year, $this->config['week'])->startOfWeek();
            $end = $start->copy()->endOfWeek();

            return new ResolvedDateRange($start, $end);
        }

        // Handle specific year
        if (isset($this->config['year'])) {
            /** @var Carbon $start */
            $start = Carbon::create($this->config['year'], 1, 1);
            /** @var Carbon $end */
            $end = Carbon::create($this->config['year'], 12, 31);

            return new ResolvedDateRange($start->startOfYear(), $end->endOfYear());
        }

        // Handle unit offset (unitAgo)
        if (isset($this->config['offset'])) {
            $offset = (int) $this->config['offset'];
            $target = $this->subtractUnits($ref->copy(), abs($offset), $unit);
            $start = $this->startOfUnit($target->copy(), $unit);
            $end = $this->endOfUnit($target->copy(), $unit);

            return new ResolvedDateRange($start, $end);
        }

        throw new InvalidArgumentException('Invalid SPECIFIC configuration');
    }

    /**
     * Resolve ANNUAL_RANGE type.
     */
    protected function resolveAnnualRange(Carbon $ref): ResolvedDateRange
    {
        $startMonth = $this->config['startMonth'];
        $yearOffset = $this->config['yearOffset'] ?? 0;

        // Determine which annual period we're currently in
        $currentAnnualYear = $ref->month >= $startMonth ? $ref->year : $ref->year - 1;
        $targetYear = $currentAnnualYear + $yearOffset;

        /** @var Carbon $start */
        $start = Carbon::create($targetYear, $startMonth, 1);
        $start = $start->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return new ResolvedDateRange($start, $end);
    }

    /**
     * Resolve CUSTOM type.
     */
    protected function resolveCustom(): ResolvedDateRange
    {
        $start = isset($this->config['start'])
            ? Carbon::parse($this->config['start'])->startOfDay()
            : null;

        $end = isset($this->config['end'])
            ? Carbon::parse($this->config['end'])->endOfDay()
            : null;

        return new ResolvedDateRange($start, $end);
    }

    /**
     * Resolve EXPRESSION type.
     *
     * Requires symfony/expression-language package for full functionality.
     * Falls back to PHP DateTime natural language parsing.
     */
    protected function resolveExpression(Carbon $ref): ResolvedDateRange
    {
        // Range expression with start and end
        if (isset($this->config['startExpression'], $this->config['endExpression'])) {
            $start = $this->evaluateExpression($this->config['startExpression'], $ref);
            $end = $this->evaluateExpression($this->config['endExpression'], $ref);

            return new ResolvedDateRange($start?->startOfDay(), $end?->endOfDay());
        }

        // Single expression (returns a range)
        if (isset($this->config['expression'])) {
            $result = $this->evaluateExpression($this->config['expression'], $ref);

            if ($result !== null) {
                return new ResolvedDateRange($result->copy()->startOfDay(), $result->copy()->endOfDay());
            }
        }

        throw new InvalidArgumentException('Invalid EXPRESSION configuration');
    }

    /**
     * Evaluate a single expression string.
     */
    protected function evaluateExpression(string $expression, Carbon $ref): ?Carbon
    {
        // Try PHP DateTime natural language first
        try {
            return Carbon::parse($expression, $ref->timezone);
        } catch (\Exception) {
            // Fall through to return null
        }

        return null;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Subtract units from a Carbon date.
     */
    protected function subtractUnits(Carbon $date, int $amount, DateUnit $unit): Carbon
    {
        return match ($unit) {
            DateUnit::DAY => $date->subDays($amount),
            DateUnit::WEEK => $date->subWeeks($amount),
            DateUnit::MONTH => $date->subMonths($amount),
            DateUnit::QUARTER => $date->subQuarters($amount),
            DateUnit::HALF_YEAR => $date->subMonths($amount * 6),
            DateUnit::YEAR => $date->subYears($amount),
        };
    }

    /**
     * Add units to a Carbon date.
     */
    protected function addUnits(Carbon $date, int $amount, DateUnit $unit): Carbon
    {
        return match ($unit) {
            DateUnit::DAY => $date->addDays($amount),
            DateUnit::WEEK => $date->addWeeks($amount),
            DateUnit::MONTH => $date->addMonths($amount),
            DateUnit::QUARTER => $date->addQuarters($amount),
            DateUnit::HALF_YEAR => $date->addMonths($amount * 6),
            DateUnit::YEAR => $date->addYears($amount),
        };
    }

    /**
     * Get start of unit for a Carbon date.
     */
    protected function startOfUnit(Carbon $date, DateUnit $unit): Carbon
    {
        return match ($unit) {
            DateUnit::DAY => $date->startOfDay(),
            DateUnit::WEEK => $date->startOfWeek(),
            DateUnit::MONTH => $date->startOfMonth(),
            DateUnit::QUARTER => $date->startOfQuarter(),
            DateUnit::HALF_YEAR => $date->month <= 6
                ? $date->setMonth(1)->startOfMonth()
                : $date->setMonth(7)->startOfMonth(),
            DateUnit::YEAR => $date->startOfYear(),
        };
    }

    /**
     * Get end of unit for a Carbon date.
     */
    protected function endOfUnit(Carbon $date, DateUnit $unit): Carbon
    {
        return match ($unit) {
            DateUnit::DAY => $date->endOfDay(),
            DateUnit::WEEK => $date->endOfWeek(),
            DateUnit::MONTH => $date->endOfMonth(),
            DateUnit::QUARTER => $date->endOfQuarter(),
            DateUnit::HALF_YEAR => $date->month <= 6
                ? $date->setMonth(6)->endOfMonth()
                : $date->setMonth(12)->endOfMonth(),
            DateUnit::YEAR => $date->endOfYear(),
        };
    }

    // =========================================================================
    // LABEL GENERATION
    // =========================================================================

    /**
     * Generate a human-readable label for this date range.
     */
    public function toLabel(): string
    {
        return match ($this->type) {
            DateRangeType::QUICK => $this->quickLabel(),
            DateRangeType::RELATIVE => $this->relativeLabel(),
            DateRangeType::SPECIFIC => $this->specificLabel(),
            DateRangeType::ANNUAL_RANGE => $this->annualRangeLabel(),
            DateRangeType::CUSTOM => $this->customLabel(),
            DateRangeType::EXPRESSION => $this->expressionLabel(),
        };
    }

    /**
     * Generate label for QUICK type.
     */
    protected function quickLabel(): string
    {
        $quickRange = QuickDateRange::from($this->config['quick']);

        return $quickRange->label();
    }

    /**
     * Generate label for RELATIVE type.
     */
    protected function relativeLabel(): string
    {
        $amount = $this->config['amount'];
        $unit = DateUnit::from($this->config['unit']);
        $direction = $this->config['direction'] ?? 'past';
        $openStart = $this->config['openStart'] ?? false;
        $endDate = $this->config['endDate'] ?? null;

        // Older than X
        if ($openStart) {
            return __('filter-core::date_range.older_than', [
                'amount' => $amount,
                'unit' => $this->unitLabel($unit, $amount),
            ]);
        }

        // Newer than X
        if ($endDate === 'now') {
            return __('filter-core::date_range.newer_than', [
                'amount' => $amount,
                'unit' => $this->unitLabel($unit, $amount),
            ]);
        }

        // Last/Next X units
        if ($direction === 'past') {
            return __('filter-core::date_range.last_x', [
                'amount' => $amount,
                'unit' => $this->unitLabel($unit, $amount),
            ]);
        }

        return __('filter-core::date_range.next_x', [
            'amount' => $amount,
            'unit' => $this->unitLabel($unit, $amount),
        ]);
    }

    /**
     * Generate label for SPECIFIC type.
     */
    protected function specificLabel(): string
    {
        $unit = DateUnit::from($this->config['unit']);
        $yearOffset = $this->config['yearOffset'] ?? 0;

        // Half year (H1/H2)
        if ($unit === DateUnit::HALF_YEAR || (isset($this->config['startMonth']) && isset($this->config['endMonth']))) {
            $half = ($this->config['startMonth'] ?? 1) <= 6 ? 1 : 2;

            return __('filter-core::date_range.half_year', [
                'half' => $half,
                'year' => $this->yearOffsetLabel($yearOffset),
            ]);
        }

        // Quarter
        if (isset($this->config['quarter'])) {
            return __('filter-core::date_range.quarter', [
                'quarter' => $this->config['quarter'],
                'year' => $this->yearOffsetLabel($yearOffset),
            ]);
        }

        // Month
        if (isset($this->config['month'])) {
            $monthName = Carbon::create(null, $this->config['month'], 1)?->translatedFormat('F') ?? '';

            return __('filter-core::date_range.month', [
                'month' => $monthName,
                'year' => $this->yearOffsetLabel($yearOffset),
            ]);
        }

        // Week
        if (isset($this->config['week'])) {
            return __('filter-core::date_range.week', [
                'week' => $this->config['week'],
                'year' => $this->yearOffsetLabel($yearOffset),
            ]);
        }

        // Year
        if (isset($this->config['year'])) {
            return (string) $this->config['year'];
        }

        // Unit ago (e.g., "2 months ago")
        if (isset($this->config['offset'])) {
            $offset = abs((int) $this->config['offset']);

            return __('filter-core::date_range.unit_ago', [
                'amount' => $offset,
                'unit' => $this->unitLabel($unit, $offset),
            ]);
        }

        return __('filter-core::date_range.specific_period');
    }

    /**
     * Generate label for ANNUAL_RANGE type.
     */
    protected function annualRangeLabel(): string
    {
        $startMonth = $this->config['startMonth'];
        $yearOffset = $this->config['yearOffset'] ?? 0;
        $rangeType = $this->config['rangeType'] ?? 'fiscal';

        $startMonthName = Carbon::create(null, $startMonth, 1)?->translatedFormat('M') ?? '';
        $endMonth = $startMonth === 1 ? 12 : $startMonth - 1;
        $endMonthName = Carbon::create(null, $endMonth, 1)?->translatedFormat('M') ?? '';

        $typeName = match ($rangeType) {
            'academic' => __('filter-core::date_range.academic_year'),
            default => __('filter-core::date_range.fiscal_year'),
        };

        if ($yearOffset === 0) {
            return "{$typeName} ({$startMonthName}-{$endMonthName})";
        }

        $yearLabel = $this->yearOffsetLabel($yearOffset);

        return "{$typeName} {$yearLabel} ({$startMonthName}-{$endMonthName})";
    }

    /**
     * Generate label for CUSTOM type.
     */
    protected function customLabel(): string
    {
        $start = $this->config['start'] ?? null;
        $end = $this->config['end'] ?? null;

        if ($start && $end) {
            $startDate = Carbon::parse($start)->translatedFormat('d.m.Y');
            $endDate = Carbon::parse($end)->translatedFormat('d.m.Y');

            return "{$startDate} - {$endDate}";
        }

        if ($start) {
            $startDate = Carbon::parse($start)->translatedFormat('d.m.Y');

            return __('filter-core::date_range.from_date', ['date' => $startDate]);
        }

        if ($end) {
            $endDate = Carbon::parse($end)->translatedFormat('d.m.Y');

            return __('filter-core::date_range.until_date', ['date' => $endDate]);
        }

        return __('filter-core::date_range.custom_range');
    }

    /**
     * Generate label for EXPRESSION type.
     */
    protected function expressionLabel(): string
    {
        if (isset($this->config['startExpression'], $this->config['endExpression'])) {
            return "{$this->config['startExpression']} - {$this->config['endExpression']}";
        }

        return $this->config['expression'] ?? __('filter-core::date_range.expression');
    }

    /**
     * Get translated unit label (singular/plural).
     */
    protected function unitLabel(DateUnit $unit, int $amount): string
    {
        $key = $amount === 1 ? 'singular' : 'plural';

        return __("filter-core::date_range.units.{$unit->value}.{$key}");
    }

    /**
     * Get year offset label.
     */
    protected function yearOffsetLabel(int $offset): string
    {
        return match ($offset) {
            0 => __('filter-core::date_range.this_year_short'),
            -1 => __('filter-core::date_range.last_year_short'),
            -2 => __('filter-core::date_range.years_ago', ['years' => 2]),
            default => $offset < 0
                ? __('filter-core::date_range.years_ago', ['years' => abs($offset)])
                : __('filter-core::date_range.years_ahead', ['years' => $offset]),
        };
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            ...$this->config,
        ];
    }

    /**
     * Create from array (e.g., from JSON).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = DateRangeType::from($data['type']);
        $config = $data;
        unset($config['type']);

        return new self($type, $config);
    }

    /**
     * Get the type of this date range.
     */
    public function getType(): DateRangeType
    {
        return $this->type;
    }

    /**
     * Get the configuration array.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
