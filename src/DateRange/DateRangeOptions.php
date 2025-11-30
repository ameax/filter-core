<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

/**
 * Helper class for generating UI options for date filters.
 */
final class DateRangeOptions
{
    /**
     * Get quick date range options for UI.
     *
     * @param  array<DateDirection>|null  $allowedDirections  Filter by direction (null = all)
     * @param  bool  $includeToday  Include today option even if FUTURE is not allowed
     * @return array<string, string> Key => Label pairs
     */
    public static function getQuickOptions(
        ?array $allowedDirections = null,
        bool $includeToday = true
    ): array {
        $options = [];

        // Day-based
        if ($includeToday || $allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::TODAY->value] = QuickDateRange::TODAY->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::YESTERDAY->value] = QuickDateRange::YESTERDAY->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::TOMORROW->value] = QuickDateRange::TOMORROW->label();
        }

        // Week-based
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::THIS_WEEK->value] = QuickDateRange::THIS_WEEK->label();
            $options[QuickDateRange::LAST_WEEK->value] = QuickDateRange::LAST_WEEK->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::NEXT_WEEK->value] = QuickDateRange::NEXT_WEEK->label();
        }

        // Month-based
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::THIS_MONTH->value] = QuickDateRange::THIS_MONTH->label();
            $options[QuickDateRange::LAST_MONTH->value] = QuickDateRange::LAST_MONTH->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::NEXT_MONTH->value] = QuickDateRange::NEXT_MONTH->label();
        }

        // Quarter-based
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::THIS_QUARTER->value] = QuickDateRange::THIS_QUARTER->label();
            $options[QuickDateRange::LAST_QUARTER->value] = QuickDateRange::LAST_QUARTER->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::NEXT_QUARTER->value] = QuickDateRange::NEXT_QUARTER->label();
        }

        // Specific quarters
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::Q1_THIS_YEAR->value] = QuickDateRange::Q1_THIS_YEAR->label();
            $options[QuickDateRange::Q2_THIS_YEAR->value] = QuickDateRange::Q2_THIS_YEAR->label();
            $options[QuickDateRange::Q3_THIS_YEAR->value] = QuickDateRange::Q3_THIS_YEAR->label();
            $options[QuickDateRange::Q4_THIS_YEAR->value] = QuickDateRange::Q4_THIS_YEAR->label();
        }

        // Half-year-based
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::THIS_HALF_YEAR->value] = QuickDateRange::THIS_HALF_YEAR->label();
            $options[QuickDateRange::LAST_HALF_YEAR->value] = QuickDateRange::LAST_HALF_YEAR->label();
            $options[QuickDateRange::H1_THIS_YEAR->value] = QuickDateRange::H1_THIS_YEAR->label();
            $options[QuickDateRange::H2_THIS_YEAR->value] = QuickDateRange::H2_THIS_YEAR->label();
            $options[QuickDateRange::H1_LAST_YEAR->value] = QuickDateRange::H1_LAST_YEAR->label();
            $options[QuickDateRange::H2_LAST_YEAR->value] = QuickDateRange::H2_LAST_YEAR->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::NEXT_HALF_YEAR->value] = QuickDateRange::NEXT_HALF_YEAR->label();
        }

        // Year-based
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::THIS_YEAR->value] = QuickDateRange::THIS_YEAR->label();
            $options[QuickDateRange::LAST_YEAR->value] = QuickDateRange::LAST_YEAR->label();
        }

        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::NEXT_YEAR->value] = QuickDateRange::NEXT_YEAR->label();
        }

        // Rolling periods (past)
        if ($allowedDirections === null || in_array(DateDirection::PAST, $allowedDirections, true)) {
            $options[QuickDateRange::LAST_7_DAYS->value] = QuickDateRange::LAST_7_DAYS->label();
            $options[QuickDateRange::LAST_14_DAYS->value] = QuickDateRange::LAST_14_DAYS->label();
            $options[QuickDateRange::LAST_30_DAYS->value] = QuickDateRange::LAST_30_DAYS->label();
            $options[QuickDateRange::LAST_60_DAYS->value] = QuickDateRange::LAST_60_DAYS->label();
            $options[QuickDateRange::LAST_90_DAYS->value] = QuickDateRange::LAST_90_DAYS->label();
            $options[QuickDateRange::LAST_365_DAYS->value] = QuickDateRange::LAST_365_DAYS->label();
        }

        // Rolling periods (future)
        if ($allowedDirections === null || in_array(DateDirection::FUTURE, $allowedDirections, true)) {
            $options[QuickDateRange::NEXT_7_DAYS->value] = QuickDateRange::NEXT_7_DAYS->label();
            $options[QuickDateRange::NEXT_14_DAYS->value] = QuickDateRange::NEXT_14_DAYS->label();
            $options[QuickDateRange::NEXT_30_DAYS->value] = QuickDateRange::NEXT_30_DAYS->label();
        }

        return $options;
    }

    /**
     * Get quick options grouped by category.
     *
     * @param  array<DateDirection>|null  $allowedDirections
     * @return array<string, array<string, string>>
     */
    public static function getGroupedQuickOptions(?array $allowedDirections = null): array
    {
        $groups = QuickDateRange::grouped();
        $result = [];

        foreach ($groups as $category => $ranges) {
            $options = [];
            foreach ($ranges as $range) {
                if ($allowedDirections !== null && ! in_array($range->direction(), $allowedDirections, true)) {
                    continue;
                }
                $options[$range->value] = $range->label();
            }

            if (! empty($options)) {
                $result[$category] = $options;
            }
        }

        return $result;
    }

    /**
     * Get date unit options for relative ranges.
     *
     * @return array<string, string>
     */
    public static function getUnitOptions(): array
    {
        return [
            DateUnit::DAY->value => DateUnit::DAY->labelPlural(),
            DateUnit::WEEK->value => DateUnit::WEEK->labelPlural(),
            DateUnit::MONTH->value => DateUnit::MONTH->labelPlural(),
            DateUnit::QUARTER->value => DateUnit::QUARTER->labelPlural(),
            DateUnit::HALF_YEAR->value => DateUnit::HALF_YEAR->labelPlural(),
            DateUnit::YEAR->value => DateUnit::YEAR->labelPlural(),
        ];
    }

    /**
     * Get direction options.
     *
     * @return array<string, string>
     */
    public static function getDirectionOptions(): array
    {
        return [
            DateDirection::PAST->value => DateDirection::PAST->label(),
            DateDirection::FUTURE->value => DateDirection::FUTURE->label(),
        ];
    }

    /**
     * Get date range type options.
     *
     * @return array<string, string>
     */
    public static function getTypeOptions(): array
    {
        return [
            DateRangeType::QUICK->value => DateRangeType::QUICK->label(),
            DateRangeType::RELATIVE->value => DateRangeType::RELATIVE->label(),
            DateRangeType::SPECIFIC->value => DateRangeType::SPECIFIC->label(),
            DateRangeType::ANNUAL_RANGE->value => DateRangeType::ANNUAL_RANGE->label(),
            DateRangeType::CUSTOM->value => DateRangeType::CUSTOM->label(),
            DateRangeType::EXPRESSION->value => DateRangeType::EXPRESSION->label(),
        ];
    }

    /**
     * Get month options for specific month selection.
     *
     * @return array<int, string>
     */
    public static function getMonthOptions(): array
    {
        return [
            1 => __('filter-core::enums.months.january'),
            2 => __('filter-core::enums.months.february'),
            3 => __('filter-core::enums.months.march'),
            4 => __('filter-core::enums.months.april'),
            5 => __('filter-core::enums.months.may'),
            6 => __('filter-core::enums.months.june'),
            7 => __('filter-core::enums.months.july'),
            8 => __('filter-core::enums.months.august'),
            9 => __('filter-core::enums.months.september'),
            10 => __('filter-core::enums.months.october'),
            11 => __('filter-core::enums.months.november'),
            12 => __('filter-core::enums.months.december'),
        ];
    }

    /**
     * Get quarter options.
     *
     * @return array<int, string>
     */
    public static function getQuarterOptions(): array
    {
        return [
            1 => __('filter-core::enums.quarters.q1'),
            2 => __('filter-core::enums.quarters.q2'),
            3 => __('filter-core::enums.quarters.q3'),
            4 => __('filter-core::enums.quarters.q4'),
        ];
    }

    /**
     * Get half-year options.
     *
     * @return array<int, string>
     */
    public static function getHalfYearOptions(): array
    {
        return [
            1 => __('filter-core::enums.half_years.h1'),
            2 => __('filter-core::enums.half_years.h2'),
        ];
    }

    /**
     * Get year offset options (common selections).
     *
     * @param  int  $yearsBack  How many years back to show
     * @param  int  $yearsForward  How many years forward to show
     * @return array<int, string>
     */
    public static function getYearOffsetOptions(int $yearsBack = 5, int $yearsForward = 2): array
    {
        $options = [];
        $currentYear = (int) date('Y');

        for ($offset = -$yearsBack; $offset <= $yearsForward; $offset++) {
            $year = $currentYear + $offset;
            $options[$offset] = (string) $year;
        }

        return $options;
    }
}
