<?php

namespace Ameax\FilterCore\Enums;

enum GroupOperatorEnum: string
{
    case AND = 'and';
    case OR = 'or';

    public function label(): string
    {
        return match ($this) {
            self::AND => __('filter-core::enums.group_operator.and'),
            self::OR => __('filter-core::enums.group_operator.or'),
        };
    }

    public function sqlKeyword(): string
    {
        return match ($this) {
            self::AND => 'AND',
            self::OR => 'OR',
        };
    }
}
