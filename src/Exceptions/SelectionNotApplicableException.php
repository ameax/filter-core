<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Exceptions;

use Ameax\FilterCore\Resolution\Diagnostics;
use RuntimeException;

/**
 * Strict resolution found conditions that cannot be applied.
 */
final class SelectionNotApplicableException extends RuntimeException
{
    public function __construct(public readonly Diagnostics $diagnostics)
    {
        $keys = implode(', ', $diagnostics->filterKeys());

        parent::__construct("The selection contains {$diagnostics->count()} condition(s) that cannot be applied: {$keys}");
    }
}
