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
}
