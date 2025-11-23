<?php

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;

/**
 * Base class for TEXT type filters.
 */
abstract class TextFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::TEXT;
    }

    public function defaultMode(): MatchModeEnum
    {
        return MatchModeEnum::CONTAINS;
    }

    public function allowedModes(): array
    {
        return [
            MatchModeEnum::CONTAINS,
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
        ];
    }
}
