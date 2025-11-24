<?php

namespace Ameax\FilterCore\Enums;

enum FilterTypeEnum: string
{
    // Phase 1
    case SELECT = 'select';
    case INTEGER = 'integer';
    case TEXT = 'text';
    case BOOLEAN = 'boolean';

    // Phase 2 (uncomment when needed)
    // case MULTI_SELECT = 'multi_select';
    // case DECIMAL = 'decimal';
    // case DATE = 'date';
    // case DATETIME = 'datetime';

    // Phase 2: Uncomment when MULTI_SELECT is enabled
    // /**
    //  * Indicates if the underlying data column stores multiple values (JSON array, comma-separated).
    //  */
    // public function isMultiValueColumn(): bool
    // {
    //     return $this === self::MULTI_SELECT;
    // }

    public function label(): string
    {
        return match ($this) {
            self::SELECT => __('filter-core::enums.filter_type.select'),
            self::INTEGER => __('filter-core::enums.filter_type.integer'),
            self::TEXT => __('filter-core::enums.filter_type.text'),
            self::BOOLEAN => __('filter-core::enums.filter_type.boolean'),
        };
    }
}
