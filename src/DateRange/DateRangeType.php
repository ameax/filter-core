<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

/**
 * Main categories of date range selection.
 */
enum DateRangeType: string
{
    case QUICK = 'quick';
    case RELATIVE = 'relative';
    case SPECIFIC = 'specific';
    case ANNUAL_RANGE = 'annual_range';
    case CUSTOM = 'custom';
    case EXPRESSION = 'expression';

    public function label(): string
    {
        return match ($this) {
            self::QUICK => __('filter-core::enums.date_range_type.quick'),
            self::RELATIVE => __('filter-core::enums.date_range_type.relative'),
            self::SPECIFIC => __('filter-core::enums.date_range_type.specific'),
            self::ANNUAL_RANGE => __('filter-core::enums.date_range_type.annual_range'),
            self::CUSTOM => __('filter-core::enums.date_range_type.custom'),
            self::EXPRESSION => __('filter-core::enums.date_range_type.expression'),
        };
    }
}
