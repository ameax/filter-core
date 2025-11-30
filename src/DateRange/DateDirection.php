<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

/**
 * Direction restriction for date filters.
 */
enum DateDirection: string
{
    case PAST = 'past';
    case FUTURE = 'future';

    public function label(): string
    {
        return match ($this) {
            self::PAST => __('filter-core::enums.date_direction.past'),
            self::FUTURE => __('filter-core::enums.date_direction.future'),
        };
    }
}
