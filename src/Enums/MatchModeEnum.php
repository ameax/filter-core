<?php

namespace Ameax\FilterCore\Enums;

enum MatchModeEnum: string
{
    // Phase 1: Equality
    case IS = 'is';
    case IS_NOT = 'is_not';

    // Phase 1: Multi-Value Logic
    case ANY = 'any';
    case NONE = 'none';

    // Phase 1: Comparison
    case GREATER_THAN = 'gt';
    case LESS_THAN = 'lt';
    case BETWEEN = 'between';

    // Phase 1: Text Matching
    case CONTAINS = 'contains';

    // Phase 1: Null Handling
    case EMPTY = 'empty';
    case NOT_EMPTY = 'not_empty';

    // Phase 2 (uncomment when needed)
    // case ALL = 'all';
    // case GREATER_THAN_OR_EQUAL = 'gte';
    // case LESS_THAN_OR_EQUAL = 'lte';
    // case STARTS_WITH = 'starts_with';
    // case ENDS_WITH = 'ends_with';

    public function label(): string
    {
        return match ($this) {
            self::IS => __('filter-core::enums.match_mode.is'),
            self::IS_NOT => __('filter-core::enums.match_mode.is_not'),
            self::ANY => __('filter-core::enums.match_mode.any'),
            self::NONE => __('filter-core::enums.match_mode.none'),
            self::GREATER_THAN => __('filter-core::enums.match_mode.greater_than'),
            self::LESS_THAN => __('filter-core::enums.match_mode.less_than'),
            self::BETWEEN => __('filter-core::enums.match_mode.between'),
            self::CONTAINS => __('filter-core::enums.match_mode.contains'),
            self::EMPTY => __('filter-core::enums.match_mode.empty'),
            self::NOT_EMPTY => __('filter-core::enums.match_mode.not_empty'),
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::IS => '=',
            self::IS_NOT => '≠',
            self::ANY => '∈',
            self::NONE => '∉',
            self::GREATER_THAN => '>',
            self::LESS_THAN => '<',
            self::BETWEEN => '↔',
            self::CONTAINS => '⊃',
            self::EMPTY => '∅',
            self::NOT_EMPTY => '!∅',
        };
    }

    public function supportsMultipleValues(): bool
    {
        return match ($this) {
            self::IS, self::IS_NOT, self::ANY, self::NONE => true,
            default => false,
        };
    }

    public function requiresRange(): bool
    {
        return $this === self::BETWEEN;
    }

    public function requiresNoValue(): bool
    {
        return match ($this) {
            self::EMPTY, self::NOT_EMPTY => true,
            default => false,
        };
    }
}
