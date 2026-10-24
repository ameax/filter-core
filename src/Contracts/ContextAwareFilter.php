<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Contracts;

use Ameax\FilterCore\Resolution\FilterContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * A filter whose query depends on who asks or where: permissions, tenant, time zone.
 *
 * When a FilterContext is given (QueryApplicator::withContext()), applyWithContext()
 * is called instead of Filter::apply(); without a context, apply() stays in charge.
 * Return true when custom logic was applied, false for the default behaviour.
 */
interface ContextAwareFilter
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function applyWithContext(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value, FilterContext $context): bool;
}
