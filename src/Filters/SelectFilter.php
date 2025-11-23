<?php

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;

/**
 * Base class for SELECT type filters.
 */
abstract class SelectFilter extends Filter implements HasOptions
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::SELECT;
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
            MatchModeEnum::ANY,
            MatchModeEnum::NONE,
        ];
    }
}
