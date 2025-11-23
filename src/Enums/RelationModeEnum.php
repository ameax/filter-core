<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Enums;

/**
 * Defines how a relation filter should be applied.
 */
enum RelationModeEnum: string
{
    /**
     * Filter records that HAVE a matching relation.
     * Uses: whereHas()
     *
     * Example: Kois that have a pond with water_type = 'fresh'
     */
    case HAS = 'has';

    /**
     * Filter records that DON'T HAVE a matching relation.
     * Uses: whereDoesntHave()
     *
     * Example: Kois that don't have a pond with water_type = 'fresh'
     * (includes kois without any pond OR kois with a pond that's not fresh)
     */
    case DOESNT_HAVE = 'doesnt_have';

    /**
     * Filter records that have NO relation at all.
     * Uses: whereDoesntHave() without condition
     *
     * Example: Kois without any pond (pond_id IS NULL)
     */
    case HAS_NONE = 'has_none';

    public function label(): string
    {
        return match ($this) {
            self::HAS => 'has',
            self::DOESNT_HAVE => 'doesn\'t have',
            self::HAS_NONE => 'has no',
        };
    }
}
