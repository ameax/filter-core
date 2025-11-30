<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

/**
 * Time units for date calculations.
 */
enum DateUnit: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case HALF_YEAR = 'half_year';
    case YEAR = 'year';

    public function label(): string
    {
        return match ($this) {
            self::DAY => __('filter-core::enums.date_unit.day'),
            self::WEEK => __('filter-core::enums.date_unit.week'),
            self::MONTH => __('filter-core::enums.date_unit.month'),
            self::QUARTER => __('filter-core::enums.date_unit.quarter'),
            self::HALF_YEAR => __('filter-core::enums.date_unit.half_year'),
            self::YEAR => __('filter-core::enums.date_unit.year'),
        };
    }

    public function labelPlural(): string
    {
        return match ($this) {
            self::DAY => __('filter-core::enums.date_unit.days'),
            self::WEEK => __('filter-core::enums.date_unit.weeks'),
            self::MONTH => __('filter-core::enums.date_unit.months'),
            self::QUARTER => __('filter-core::enums.date_unit.quarters'),
            self::HALF_YEAR => __('filter-core::enums.date_unit.half_years'),
            self::YEAR => __('filter-core::enums.date_unit.years'),
        };
    }

    /**
     * Get the Carbon method suffix for this unit.
     */
    public function carbonMethod(): string
    {
        return match ($this) {
            self::DAY => 'Day',
            self::WEEK => 'Week',
            self::MONTH => 'Month',
            self::QUARTER => 'Quarter',
            self::HALF_YEAR => 'Month', // Handled specially with *6
            self::YEAR => 'Year',
        };
    }

    /**
     * Get the number of months this unit represents.
     */
    public function toMonths(): int
    {
        return match ($this) {
            self::DAY => 0,
            self::WEEK => 0,
            self::MONTH => 1,
            self::QUARTER => 3,
            self::HALF_YEAR => 6,
            self::YEAR => 12,
        };
    }
}
