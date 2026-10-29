<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

/**
 * Why a condition of a selection cannot be applied.
 */
enum DiagnosticCode: string
{
    /** No filter with this key is registered (deleted, renamed, not permitted). */
    case UnknownFilter = 'unknown_filter';

    /** The match mode is not known at all (see UnresolvedMatchMode). */
    case UnknownMode = 'unknown_mode';

    /** The match mode exists, but this filter does not offer it. */
    case ModeNotAllowed = 'mode_not_allowed';

    /** The value fails the filter's type check or validation, e.g. a deleted option. */
    case InvalidValue = 'invalid_value';

    /** The filter belongs to another relation than the group it stands in. */
    case OutOfScope = 'out_of_scope';

    /** The group uses a relation that no registered filter declares. */
    case UnknownRelation = 'unknown_relation';
}
