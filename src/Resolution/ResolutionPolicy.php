<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

/**
 * What happens when a condition of a selection cannot be applied.
 *
 * The caller chooses, not the selection: a list may leave out a stale condition and
 * tell the user, a job that deletes or exports data must not run on a selection that
 * no longer means what it meant when it was saved.
 */
enum ResolutionPolicy: string
{
    /** Any diagnostic aborts: SelectionNotApplicableException. */
    case Strict = 'strict';

    /** The condition is left out, empty groups are removed, the diagnostic is kept. */
    case Lenient = 'lenient';

    /**
     * The condition matches nothing, the diagnostic is kept.
     *
     * Never widens the result: in an AND group the group matches nothing, in an OR
     * group only that branch drops out. Use it where leaving a condition out would
     * show records that the saved selection was meant to exclude.
     */
    case Restrictive = 'restrictive';
}
