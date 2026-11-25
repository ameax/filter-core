<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;

/**
 * The negated match modes and the positive mode each one negates.
 *
 * Across a relation a negated mode must hold for every related record: "is empty"
 * means no related record has a value, not that one related record has none. The
 * applicators therefore turn a negated mode into "has no related record matching
 * the positive mode".
 */
final class MatchModeNegation
{
    /**
     * The mode a negated mode negates, or null for a mode that is not negated.
     */
    public static function positiveOf(MatchModeContract $mode): ?MatchModeContract
    {
        return match (true) {
            $mode instanceof EmptyMatchMode => new NotEmptyMatchMode,
            $mode instanceof IsNotMatchMode => new IsMatchMode,
            $mode instanceof NoneMatchMode => new AnyMatchMode,
            $mode instanceof NotInDateRangeMatchMode => new DateRangeMatchMode,
            default => null,
        };
    }
}
