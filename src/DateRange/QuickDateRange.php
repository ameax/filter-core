<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

use Carbon\Carbon;

/**
 * Predefined quick date range selections.
 */
enum QuickDateRange: string
{
    // Day-based
    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case TOMORROW = 'tomorrow';

    // Week-based
    case THIS_WEEK = 'this_week';
    case LAST_WEEK = 'last_week';
    case NEXT_WEEK = 'next_week';

    // Month-based
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case NEXT_MONTH = 'next_month';

    // Quarter-based
    case THIS_QUARTER = 'this_quarter';
    case LAST_QUARTER = 'last_quarter';
    case NEXT_QUARTER = 'next_quarter';
    case Q1_THIS_YEAR = 'q1_this_year';
    case Q2_THIS_YEAR = 'q2_this_year';
    case Q3_THIS_YEAR = 'q3_this_year';
    case Q4_THIS_YEAR = 'q4_this_year';

    // Half-year-based
    case THIS_HALF_YEAR = 'this_half_year';
    case LAST_HALF_YEAR = 'last_half_year';
    case NEXT_HALF_YEAR = 'next_half_year';
    case H1_THIS_YEAR = 'h1_this_year';
    case H2_THIS_YEAR = 'h2_this_year';
    case H1_LAST_YEAR = 'h1_last_year';
    case H2_LAST_YEAR = 'h2_last_year';

    // Year-based
    case THIS_YEAR = 'this_year';
    case LAST_YEAR = 'last_year';
    case NEXT_YEAR = 'next_year';

    // Rolling periods
    case LAST_7_DAYS = 'last_7_days';
    case LAST_14_DAYS = 'last_14_days';
    case LAST_30_DAYS = 'last_30_days';
    case LAST_60_DAYS = 'last_60_days';
    case LAST_90_DAYS = 'last_90_days';
    case LAST_365_DAYS = 'last_365_days';
    case NEXT_7_DAYS = 'next_7_days';
    case NEXT_14_DAYS = 'next_14_days';
    case NEXT_30_DAYS = 'next_30_days';

    public function label(): string
    {
        return match ($this) {
            self::TODAY => __('filter-core::enums.quick_date_range.today'),
            self::YESTERDAY => __('filter-core::enums.quick_date_range.yesterday'),
            self::TOMORROW => __('filter-core::enums.quick_date_range.tomorrow'),
            self::THIS_WEEK => __('filter-core::enums.quick_date_range.this_week'),
            self::LAST_WEEK => __('filter-core::enums.quick_date_range.last_week'),
            self::NEXT_WEEK => __('filter-core::enums.quick_date_range.next_week'),
            self::THIS_MONTH => __('filter-core::enums.quick_date_range.this_month'),
            self::LAST_MONTH => __('filter-core::enums.quick_date_range.last_month'),
            self::NEXT_MONTH => __('filter-core::enums.quick_date_range.next_month'),
            self::THIS_QUARTER => __('filter-core::enums.quick_date_range.this_quarter'),
            self::LAST_QUARTER => __('filter-core::enums.quick_date_range.last_quarter'),
            self::NEXT_QUARTER => __('filter-core::enums.quick_date_range.next_quarter'),
            self::Q1_THIS_YEAR => __('filter-core::enums.quick_date_range.q1_this_year'),
            self::Q2_THIS_YEAR => __('filter-core::enums.quick_date_range.q2_this_year'),
            self::Q3_THIS_YEAR => __('filter-core::enums.quick_date_range.q3_this_year'),
            self::Q4_THIS_YEAR => __('filter-core::enums.quick_date_range.q4_this_year'),
            self::THIS_HALF_YEAR => __('filter-core::enums.quick_date_range.this_half_year'),
            self::LAST_HALF_YEAR => __('filter-core::enums.quick_date_range.last_half_year'),
            self::NEXT_HALF_YEAR => __('filter-core::enums.quick_date_range.next_half_year'),
            self::H1_THIS_YEAR => __('filter-core::enums.quick_date_range.h1_this_year'),
            self::H2_THIS_YEAR => __('filter-core::enums.quick_date_range.h2_this_year'),
            self::H1_LAST_YEAR => __('filter-core::enums.quick_date_range.h1_last_year'),
            self::H2_LAST_YEAR => __('filter-core::enums.quick_date_range.h2_last_year'),
            self::THIS_YEAR => __('filter-core::enums.quick_date_range.this_year'),
            self::LAST_YEAR => __('filter-core::enums.quick_date_range.last_year'),
            self::NEXT_YEAR => __('filter-core::enums.quick_date_range.next_year'),
            self::LAST_7_DAYS => __('filter-core::enums.quick_date_range.last_7_days'),
            self::LAST_14_DAYS => __('filter-core::enums.quick_date_range.last_14_days'),
            self::LAST_30_DAYS => __('filter-core::enums.quick_date_range.last_30_days'),
            self::LAST_60_DAYS => __('filter-core::enums.quick_date_range.last_60_days'),
            self::LAST_90_DAYS => __('filter-core::enums.quick_date_range.last_90_days'),
            self::LAST_365_DAYS => __('filter-core::enums.quick_date_range.last_365_days'),
            self::NEXT_7_DAYS => __('filter-core::enums.quick_date_range.next_7_days'),
            self::NEXT_14_DAYS => __('filter-core::enums.quick_date_range.next_14_days'),
            self::NEXT_30_DAYS => __('filter-core::enums.quick_date_range.next_30_days'),
        };
    }

    /**
     * Get the direction of this quick range.
     */
    public function direction(): DateDirection
    {
        return match ($this) {
            self::TOMORROW,
            self::NEXT_WEEK,
            self::NEXT_MONTH,
            self::NEXT_QUARTER,
            self::NEXT_HALF_YEAR,
            self::NEXT_YEAR,
            self::NEXT_7_DAYS,
            self::NEXT_14_DAYS,
            self::NEXT_30_DAYS => DateDirection::FUTURE,

            default => DateDirection::PAST,
        };
    }

    /**
     * Resolve this quick range to start and end Carbon dates.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolve(?Carbon $reference = null): array
    {
        $ref = $reference ?? Carbon::now();

        return match ($this) {
            // Day-based
            self::TODAY => [
                'start' => $ref->copy()->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],
            self::YESTERDAY => [
                'start' => $ref->copy()->subDay()->startOfDay(),
                'end' => $ref->copy()->subDay()->endOfDay(),
            ],
            self::TOMORROW => [
                'start' => $ref->copy()->addDay()->startOfDay(),
                'end' => $ref->copy()->addDay()->endOfDay(),
            ],

            // Week-based
            self::THIS_WEEK => [
                'start' => $ref->copy()->startOfWeek(),
                'end' => $ref->copy()->endOfWeek(),
            ],
            self::LAST_WEEK => [
                'start' => $ref->copy()->subWeek()->startOfWeek(),
                'end' => $ref->copy()->subWeek()->endOfWeek(),
            ],
            self::NEXT_WEEK => [
                'start' => $ref->copy()->addWeek()->startOfWeek(),
                'end' => $ref->copy()->addWeek()->endOfWeek(),
            ],

            // Month-based
            self::THIS_MONTH => [
                'start' => $ref->copy()->startOfMonth(),
                'end' => $ref->copy()->endOfMonth(),
            ],
            self::LAST_MONTH => [
                'start' => $ref->copy()->subMonth()->startOfMonth(),
                'end' => $ref->copy()->subMonth()->endOfMonth(),
            ],
            self::NEXT_MONTH => [
                'start' => $ref->copy()->addMonth()->startOfMonth(),
                'end' => $ref->copy()->addMonth()->endOfMonth(),
            ],

            // Quarter-based
            self::THIS_QUARTER => [
                'start' => $ref->copy()->startOfQuarter(),
                'end' => $ref->copy()->endOfQuarter(),
            ],
            self::LAST_QUARTER => [
                'start' => $ref->copy()->subQuarter()->startOfQuarter(),
                'end' => $ref->copy()->subQuarter()->endOfQuarter(),
            ],
            self::NEXT_QUARTER => [
                'start' => $ref->copy()->addQuarter()->startOfQuarter(),
                'end' => $ref->copy()->addQuarter()->endOfQuarter(),
            ],
            self::Q1_THIS_YEAR => [
                'start' => $ref->copy()->setMonth(1)->startOfQuarter(),
                'end' => $ref->copy()->setMonth(1)->endOfQuarter(),
            ],
            self::Q2_THIS_YEAR => [
                'start' => $ref->copy()->setMonth(4)->startOfQuarter(),
                'end' => $ref->copy()->setMonth(4)->endOfQuarter(),
            ],
            self::Q3_THIS_YEAR => [
                'start' => $ref->copy()->setMonth(7)->startOfQuarter(),
                'end' => $ref->copy()->setMonth(7)->endOfQuarter(),
            ],
            self::Q4_THIS_YEAR => [
                'start' => $ref->copy()->setMonth(10)->startOfQuarter(),
                'end' => $ref->copy()->setMonth(10)->endOfQuarter(),
            ],

            // Half-year-based
            self::THIS_HALF_YEAR => $this->resolveHalfYear($ref, 0),
            self::LAST_HALF_YEAR => $this->resolveHalfYear($ref, -1),
            self::NEXT_HALF_YEAR => $this->resolveHalfYear($ref, 1),
            self::H1_THIS_YEAR => [
                'start' => $ref->copy()->setMonth(1)->setDay(1)->startOfDay(),
                'end' => $ref->copy()->setMonth(6)->endOfMonth(),
            ],
            self::H2_THIS_YEAR => [
                'start' => $ref->copy()->setMonth(7)->setDay(1)->startOfDay(),
                'end' => $ref->copy()->setMonth(12)->endOfMonth(),
            ],
            self::H1_LAST_YEAR => [
                'start' => $ref->copy()->subYear()->setMonth(1)->setDay(1)->startOfDay(),
                'end' => $ref->copy()->subYear()->setMonth(6)->endOfMonth(),
            ],
            self::H2_LAST_YEAR => [
                'start' => $ref->copy()->subYear()->setMonth(7)->setDay(1)->startOfDay(),
                'end' => $ref->copy()->subYear()->setMonth(12)->endOfMonth(),
            ],

            // Year-based
            self::THIS_YEAR => [
                'start' => $ref->copy()->startOfYear(),
                'end' => $ref->copy()->endOfYear(),
            ],
            self::LAST_YEAR => [
                'start' => $ref->copy()->subYear()->startOfYear(),
                'end' => $ref->copy()->subYear()->endOfYear(),
            ],
            self::NEXT_YEAR => [
                'start' => $ref->copy()->addYear()->startOfYear(),
                'end' => $ref->copy()->addYear()->endOfYear(),
            ],

            // Rolling periods (past)
            self::LAST_7_DAYS => [
                'start' => $ref->copy()->subDays(6)->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],
            self::LAST_14_DAYS => [
                'start' => $ref->copy()->subDays(13)->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],
            self::LAST_30_DAYS => [
                'start' => $ref->copy()->subDays(29)->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],
            self::LAST_60_DAYS => [
                'start' => $ref->copy()->subDays(59)->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],
            self::LAST_90_DAYS => [
                'start' => $ref->copy()->subDays(89)->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],
            self::LAST_365_DAYS => [
                'start' => $ref->copy()->subDays(364)->startOfDay(),
                'end' => $ref->copy()->endOfDay(),
            ],

            // Rolling periods (future)
            self::NEXT_7_DAYS => [
                'start' => $ref->copy()->startOfDay(),
                'end' => $ref->copy()->addDays(6)->endOfDay(),
            ],
            self::NEXT_14_DAYS => [
                'start' => $ref->copy()->startOfDay(),
                'end' => $ref->copy()->addDays(13)->endOfDay(),
            ],
            self::NEXT_30_DAYS => [
                'start' => $ref->copy()->startOfDay(),
                'end' => $ref->copy()->addDays(29)->endOfDay(),
            ],
        };
    }

    /**
     * Resolve half-year with offset.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveHalfYear(Carbon $ref, int $offset): array
    {
        $currentHalf = $ref->month <= 6 ? 1 : 2;
        $year = $ref->year;

        // Apply offset
        $targetHalf = $currentHalf + $offset;
        while ($targetHalf < 1) {
            $targetHalf += 2;
            $year--;
        }
        while ($targetHalf > 2) {
            $targetHalf -= 2;
            $year++;
        }

        if ($targetHalf === 1) {
            /** @var Carbon $start */
            $start = Carbon::create($year, 1, 1);
            /** @var Carbon $end */
            $end = Carbon::create($year, 6, 30);

            return [
                'start' => $start->startOfDay(),
                'end' => $end->endOfDay(),
            ];
        }

        /** @var Carbon $start */
        $start = Carbon::create($year, 7, 1);
        /** @var Carbon $end */
        $end = Carbon::create($year, 12, 31);

        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ];
    }

    /**
     * Get all quick ranges that apply to a specific direction.
     *
     * @return array<self>
     */
    public static function forDirection(DateDirection $direction): array
    {
        return array_filter(
            self::cases(),
            fn (self $range) => $range->direction() === $direction
        );
    }

    /**
     * Get quick ranges grouped by category.
     *
     * @return array<string, array<self>>
     */
    public static function grouped(): array
    {
        return [
            'day' => [self::TODAY, self::YESTERDAY, self::TOMORROW],
            'week' => [self::THIS_WEEK, self::LAST_WEEK, self::NEXT_WEEK],
            'month' => [self::THIS_MONTH, self::LAST_MONTH, self::NEXT_MONTH],
            'quarter' => [
                self::THIS_QUARTER, self::LAST_QUARTER, self::NEXT_QUARTER,
                self::Q1_THIS_YEAR, self::Q2_THIS_YEAR, self::Q3_THIS_YEAR, self::Q4_THIS_YEAR,
            ],
            'half_year' => [
                self::THIS_HALF_YEAR, self::LAST_HALF_YEAR, self::NEXT_HALF_YEAR,
                self::H1_THIS_YEAR, self::H2_THIS_YEAR, self::H1_LAST_YEAR, self::H2_LAST_YEAR,
            ],
            'year' => [self::THIS_YEAR, self::LAST_YEAR, self::NEXT_YEAR],
            'rolling' => [
                self::LAST_7_DAYS, self::LAST_14_DAYS, self::LAST_30_DAYS,
                self::LAST_60_DAYS, self::LAST_90_DAYS, self::LAST_365_DAYS,
                self::NEXT_7_DAYS, self::NEXT_14_DAYS, self::NEXT_30_DAYS,
            ],
        ];
    }
}
