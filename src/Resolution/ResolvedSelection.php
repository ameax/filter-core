<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * A selection after resolution: only conditions that can be applied, plus the
 * findings about those that could not.
 */
final readonly class ResolvedSelection
{
    /**
     * @param  list<Filter>  $filters
     */
    public function __construct(
        private FilterSelection $selection,
        private Diagnostics $diagnostics,
        private array $filters,
        private FilterContext $context,
    ) {}

    /**
     * The selection that will be applied. In lenient mode without the left-out conditions.
     */
    public function selection(): FilterSelection
    {
        return $this->selection;
    }

    public function diagnostics(): Diagnostics
    {
        return $this->diagnostics;
    }

    public function context(): FilterContext
    {
        return $this->context;
    }

    /**
     * True when every condition of the original selection is applied.
     */
    public function isComplete(): bool
    {
        return $this->diagnostics->isEmpty();
    }

    /**
     * False when nothing is left to filter by, e.g. after leaving out every condition.
     */
    public function hasConditions(): bool
    {
        return $this->selection->hasFilters();
    }

    /**
     * Apply to a query, with the context for context-aware filters.
     *
     * @template TQuery of Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function applyTo(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        QueryApplicator::for($query)
            ->withFilters($this->filters)
            ->withContext($this->context)
            ->applySelection($this->selection);

        return $query;
    }
}
