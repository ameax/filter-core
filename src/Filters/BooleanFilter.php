<?php

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;

/**
 * Base class for BOOLEAN type filters.
 */
abstract class BooleanFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::BOOLEAN;
    }

    public function defaultMode(): MatchModeEnum
    {
        return MatchModeEnum::IS;
    }

    public function allowedModes(): array
    {
        return [
            MatchModeEnum::IS,
        ];
    }
}
