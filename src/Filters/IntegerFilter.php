<?php

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;

/**
 * Base class for INTEGER type filters.
 */
abstract class IntegerFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::INTEGER;
    }

    public function defaultMode(): MatchModeEnum
    {
        return MatchModeEnum::IS;
    }

    public function allowedModes(): array
    {
        return [
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
            MatchModeEnum::GREATER_THAN,
            MatchModeEnum::LESS_THAN,
            MatchModeEnum::BETWEEN,
        ];
    }
}
