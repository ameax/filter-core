<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicFilter;
use Ameax\FilterCore\Filters\Filter;

/**
 * Stands in for a condition that cannot be applied under the restrictive policy:
 * it matches nothing, in a query and in a collection.
 *
 * @internal
 */
final class NeverFilter extends Filter implements DynamicFilter
{
    public const KEY = '__never';

    public static function condition(): FilterValue
    {
        return FilterValue::make(self::KEY, new NeverMatchMode, null);
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function column(): string
    {
        return self::KEY;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::BOOLEAN;
    }

    public function defaultMode(): NeverMatchMode
    {
        return new NeverMatchMode;
    }

    public function allowedModes(): array
    {
        return [new NeverMatchMode];
    }
}
