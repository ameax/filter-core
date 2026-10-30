<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Query;

use Ameax\FilterCore\Contracts\ContextAwareFilter;
use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Enums\RelationModeEnum;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\MatchModes\AnyMatchMode;
use Ameax\FilterCore\MatchModes\NoneMatchMode;
use Ameax\FilterCore\Resolution\FilterContext;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Applies filter values to Eloquent queries.
 */
final class QueryApplicator
{
    /** @var array<string, Filter> */
    protected array $filters = [];

    /** @var array<string, FilterDefinition> */
    protected array $filterDefinitions = [];

    /** @var array<FilterValue> */
    protected array $appliedFilters = [];

    protected ?FilterContext $context = null;

    /** The relation whose records are being filtered (inside a relation group), if any. */
    protected ?string $currentRelation = null;

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function __construct(
        protected Builder|QueryBuilder $query,
    ) {}

    /**
     * Create a new QueryApplicator for the given query.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function for(Builder|QueryBuilder $query): self
    {
        return new self($query);
    }

    /**
     * Register filters (Filter classes or instances).
     *
     * @param  array<class-string<Filter>|Filter>  $filters
     */
    public function withFilters(array $filters): self
    {
        foreach ($filters as $filter) {
            $filterInstance = is_string($filter) ? $filter::make() : $filter;
            $key = $filterInstance->resolveKey();

            $this->filters[$key] = $filterInstance;
            $this->filterDefinitions[$key] = $filterInstance->toDefinition();
        }

        return $this;
    }

    /**
     * Pass a context to filters that implement ContextAwareFilter.
     */
    public function withContext(?FilterContext $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Run the filter's own logic: applyWithContext() when a context is set and the
     * filter takes one, apply() otherwise.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyCustom(Filter $filter, Builder|QueryBuilder $query, MatchModeContract $matchMode, mixed $value): bool
    {
        if ($this->context !== null && $filter instanceof ContextAwareFilter) {
            return $filter->applyWithContext($query, $matchMode, $value, $this->context);
        }

        return $filter->apply($query, $matchMode, $value);
    }

    /**
     * Register filter definitions (legacy support).
     *
     * @param  array<FilterDefinition>  $definitions
     */
    public function withDefinitions(array $definitions): self
    {
        foreach ($definitions as $definition) {
            $this->filterDefinitions[$definition->getKey()] = $definition;
        }

        return $this;
    }

    /**
     * Apply a single filter value to the query.
     *
     * @throws InvalidArgumentException When filter is not defined or match mode not allowed
     * @throws FilterValidationException When filter value validation fails
     */
    public function applyFilter(FilterValue $filterValue): self
    {
        $filterKey = $filterValue->getFilterKey();
        $definition = $this->filterDefinitions[$filterKey] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Filter '{$filterKey}' is not defined");
        }

        $matchMode = $filterValue->getMatchMode();

        if (! $definition->isMatchModeAllowed($matchMode)) {
            throw new InvalidArgumentException(
                "Match mode '{$matchMode->key()}' is not allowed for filter '{$filterKey}'"
            );
        }

        $column = $definition->getColumn();
        $value = $filterValue->getValue();

        // Check if this filter has a relation
        $filter = $this->filters[$filterKey] ?? null;
        $relation = $this->ownRelation($filter);

        // Sanitize, type-check, and validate value if filter instance is available
        if ($filter !== null) {
            $value = ValuePreparer::prepare($filter, $filterKey, $matchMode, $value);
        }

        // Convert BetweenValue to array for query application
        if ($value instanceof BetweenValue) {
            $value = $value->toArray();
        }

        $condition = function (Builder|QueryBuilder $q, ?MatchModeContract $mode = null) use ($filter, $matchMode, $column, $value, $relation): void {
            $mode ??= $matchMode;
            // First, check if filter has custom apply logic
            if ($filter !== null && $this->applyCustom($filter, $q, $mode, $value)) {
                // Custom logic was applied - done
            } elseif ($filter !== null && $relation !== null && $q instanceof Builder) {
                // Apply via relation with appropriate mode (whereHas, whereDoesntHave)
                $this->applyRelationFilter(
                    $q,
                    $relation,
                    $filter->getRelationMode(),
                    $column,
                    $mode,
                    $value
                );
            } else {
                // Apply match mode logic directly
                $mode->apply($q, $column, $value);
            }
        };
        $this->applyInScope($this->query, $filter, $condition, $matchMode);

        $this->appliedFilters[] = $filterValue;

        return $this;
    }

    /**
     * Apply multiple filter values to the query.
     *
     * Optimizes relation filters by grouping them and applying
     * multiple filters on the same relation in a single whereHas().
     *
     * @param  array<FilterValue>  $filterValues
     */
    public function applyFilters(array $filterValues): self
    {
        // Group filters by relation and mode
        $grouped = $this->groupFiltersByRelation($filterValues);

        // Apply direct filters (no relation)
        foreach ($grouped['direct'] as $filterValue) {
            $this->applyFilter($filterValue);
        }

        // Apply grouped relation filters
        foreach ($grouped['relations'] as $key => $filters) {
            $this->applyGroupedRelationFilters($filters, $key);
        }

        return $this;
    }

    /**
     * Group filter values by relation and mode for optimization.
     *
     * @param  array<FilterValue>  $filterValues
     * @return array{direct: array<FilterValue>, relations: array<string, array<FilterValue>>}
     */
    protected function groupFiltersByRelation(array $filterValues): array
    {
        $direct = [];
        $relations = [];

        foreach ($filterValues as $filterValue) {
            $filterKey = $filterValue->getFilterKey();
            $filter = $this->filters[$filterKey] ?? null;
            $relation = $filter?->getRelation();

            if ($filter === null || $relation === null) {
                // Direct filter (no relation or filter not found)
                $direct[] = $filterValue;
            } else {
                // Group by relation + mode (e.g., "pond:has" or "pond:doesnt_have")
                $relationMode = $filter->getRelationMode();
                $groupKey = $relation.':'.$relationMode->value;
                $relations[$groupKey][] = $filterValue;
            }
        }

        return [
            'direct' => $direct,
            'relations' => $relations,
        ];
    }

    /**
     * Apply multiple filters on the same relation in a single whereHas().
     *
     * @param  array<FilterValue>  $filterValues
     * @param  string  $groupKey  Format: "relation:mode" (e.g., "pond:has")
     */
    protected function applyGroupedRelationFilters(array $filterValues, string $groupKey): void
    {
        if (empty($filterValues)) {
            return;
        }

        // Parse group key
        [$relation, $modeValue] = explode(':', $groupKey);
        $relationMode = RelationModeEnum::from($modeValue);

        // Get first filter to determine relation details
        $firstFilterKey = $filterValues[0]->getFilterKey();
        $filter = $this->filters[$firstFilterKey] ?? null;

        if ($filter === null || ! ($this->query instanceof Builder)) {
            // Fallback to individual application
            foreach ($filterValues as $filterValue) {
                $this->applyFilter($filterValue);
            }

            return;
        }

        // Apply all filters in a single whereHas/whereDoesntHave
        $callback = function (Builder $relQuery) use ($filterValues): void {
            foreach ($filterValues as $filterValue) {
                $this->applyFilterToRelationQuery($relQuery, $filterValue);
            }
        };

        match ($relationMode) {
            RelationModeEnum::HAS => $this->query->whereHas($relation, $callback),
            RelationModeEnum::DOESNT_HAVE => $this->query->whereDoesntHave($relation, $callback),
            RelationModeEnum::HAS_NONE => $this->query->whereDoesntHave($relation),
        };

        // Track applied filters
        foreach ($filterValues as $filterValue) {
            $this->appliedFilters[] = $filterValue;
        }
    }

    /**
     * Apply a single filter to a relation query (used within whereHas callback).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $relQuery
     */
    protected function applyFilterToRelationQuery(Builder $relQuery, FilterValue $filterValue): void
    {
        $filterKey = $filterValue->getFilterKey();
        $definition = $this->filterDefinitions[$filterKey] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Filter '{$filterKey}' is not defined");
        }

        $matchMode = $filterValue->getMatchMode();

        if (! $definition->isMatchModeAllowed($matchMode)) {
            throw new InvalidArgumentException(
                "Match mode '{$matchMode->key()}' is not allowed for filter '{$filterKey}'"
            );
        }

        $column = $definition->getColumn();
        $value = $filterValue->getValue();

        $filter = $this->filters[$filterKey] ?? null;

        if ($filter !== null) {
            $value = ValuePreparer::prepare($filter, $filterKey, $matchMode, $value);
        }

        if ($value instanceof BetweenValue) {
            $value = $value->toArray();
        }

        // Check if filter has custom apply logic
        if ($filter !== null && $this->applyCustom($filter, $relQuery, $matchMode, $value)) {
            // Custom logic was applied
        } else {
            // Apply match mode logic
            $matchMode->apply($relQuery, $column, $value);
        }
    }

    /**
     * Apply a FilterSelection to the query.
     *
     * This handles complex nested AND/OR logic.
     */
    public function applySelection(FilterSelection $selection): self
    {
        if (! $selection->hasFilters()) {
            return $this;
        }

        // Always one parenthesised group: an OR selection applied straight onto a
        // query that already has conditions would read "a AND b OR c" and bypass them.
        $this->query->where(function (Builder|QueryBuilder $q) use ($selection): void {
            $this->applyGroupOrRelation($q, $selection->getGroup());
        });

        return $this;
    }

    /**
     * Apply a FilterGroup to a query (recursive).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function applyGroup(Builder|QueryBuilder $query, FilterGroup $group): void
    {
        if ($group->isEmpty()) {
            return;
        }

        $items = $group->getItems();
        $isOr = $group->getOperator() === GroupOperatorEnum::OR;

        foreach ($items as $index => $item) {
            if ($item instanceof FilterValue) {
                $this->applyFilterValueToQuery($query, $item, $isOr && $index > 0);
            } elseif ($item instanceof FilterGroup) {
                $this->applyNestedGroup($query, $item, $isOr && $index > 0);
            }
        }
    }

    /**
     * Apply a FilterValue to a query with optional OR logic.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyFilterValueToQuery(
        Builder|QueryBuilder $query,
        FilterValue $filterValue,
        bool $useOr = false
    ): void {
        $filterKey = $filterValue->getFilterKey();
        $definition = $this->filterDefinitions[$filterKey] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Filter '{$filterKey}' is not defined");
        }

        $matchMode = $filterValue->getMatchMode();

        if (! $definition->isMatchModeAllowed($matchMode)) {
            throw new InvalidArgumentException(
                "Match mode '{$matchMode->key()}' is not allowed for filter '{$filterKey}'"
            );
        }

        $column = $definition->getColumn();
        $value = $filterValue->getValue();

        $filter = $this->filters[$filterKey] ?? null;
        $relation = $this->ownRelation($filter);

        if ($filter !== null) {
            $value = ValuePreparer::prepare($filter, $filterKey, $matchMode, $value);
        }

        if ($value instanceof BetweenValue) {
            $value = $value->toArray();
        }

        // Build the condition closure
        $condition = function (Builder|QueryBuilder $q, ?MatchModeContract $mode = null) use ($filter, $matchMode, $column, $value, $relation): void {
            $mode ??= $matchMode;
            if ($filter !== null && $this->applyCustom($filter, $q, $mode, $value)) {
                // Custom logic was applied
            } elseif ($filter !== null && $relation !== null && $q instanceof Builder) {
                $this->applyRelationFilter(
                    $q,
                    $relation,
                    $filter->getRelationMode(),
                    $column,
                    $mode,
                    $value
                );
            } else {
                $mode->apply($q, $column, $value);
            }
        };

        // Apply with AND or OR
        if ($useOr) {
            $query->orWhere(fn ($q) => $this->applyInScope($q, $filter, $condition, $matchMode));
        } else {
            $query->where(fn ($q) => $this->applyInScope($q, $filter, $condition, $matchMode));
        }

        $this->appliedFilters[] = $filterValue;
    }

    /**
     * Apply a nested FilterGroup to a query.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyNestedGroup(
        Builder|QueryBuilder $query,
        FilterGroup $group,
        bool $useOr = false
    ): void {
        if ($group->isEmpty()) {
            return;
        }

        $condition = function (Builder|QueryBuilder $q) use ($group): void {
            $this->applyGroupOrRelation($q, $group);
        };

        if ($useOr) {
            $query->orWhere($condition);
        } else {
            $query->where($condition);
        }
    }

    /**
     * Apply a relation filter with the appropriate mode (whereHas, whereDoesntHave).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applyRelationFilter(
        Builder $query,
        string $relation,
        RelationModeEnum $mode,
        string $column,
        MatchModeContract $matchMode,
        mixed $value
    ): void {
        // "none of" on a HAS relation must invert: use whereDoesntHave
        // with a positive whereIn, so records having any of the specified
        // related values are excluded.
        if ($matchMode instanceof NoneMatchMode && $mode === RelationModeEnum::HAS) {
            $query->whereDoesntHave($relation, function (Builder $relQuery) use ($column, $value): void {
                $values = is_array($value) ? $value : [$value];
                $relQuery->whereIn($column, $values);
            });

            return;
        }

        $callback = function (Builder $relQuery) use ($column, $matchMode, $value): void {
            $matchMode->apply($relQuery, $column, $value);
        };

        match ($mode) {
            RelationModeEnum::HAS => $query->whereHas($relation, $callback),
            RelationModeEnum::DOESNT_HAVE => $query->whereDoesntHave($relation, $callback),
            RelationModeEnum::HAS_NONE => $query->whereDoesntHave($relation),
        };
    }

    /**
     * Apply a group; a group bound to a relation becomes one whereHas() (or
     * whereDoesntHave()) whose conditions all hold for the same related record.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyGroupOrRelation(Builder|QueryBuilder $query, FilterGroup $group): void
    {
        $relation = $group->getRelation();
        if ($relation === null) {
            $this->applyGroup($query, $group);

            return;
        }

        if (! $query instanceof Builder) {
            throw new InvalidArgumentException("The group for relation '{$relation}' needs an Eloquent query");
        }

        $this->guardRelation($relation);

        $inRelation = function (Builder $relQuery) use ($relation, $group): void {
            $this->withinRelation($relation, fn () => $this->applyGroup($relQuery, $group));
        };

        match ($group->getRelationMode()) {
            RelationModeEnum::HAS => $query->whereHas($relation, $inRelation),
            RelationModeEnum::DOESNT_HAVE => $query->whereDoesntHave($relation, $inRelation),
            RelationModeEnum::HAS_NONE => $query->whereDoesntHave($relation),
        };
    }

    /**
     * Run a condition where the filter's columns live: directly when the query already
     * filters the records of the filter's relation scope, through whereHas() otherwise.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @param  callable(Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder, ?MatchModeContract=): void  $condition  takes a match mode that replaces the filter's own
     */
    protected function applyInScope(Builder|QueryBuilder $query, ?Filter $filter, callable $condition, ?MatchModeContract $matchMode = null): void
    {
        $scope = $filter?->relationScope();
        if ($filter === null || $scope === null || $scope === $this->currentRelation) {
            $condition($query);

            return;
        }

        if (! $query instanceof Builder) {
            throw new InvalidArgumentException("Filter '{$filter->resolveKey()}' belongs to relation '{$scope}' and needs an Eloquent query");
        }

        // "None of" must hold for every related record, as for via() filters
        // (applyRelationFilter()): no related record has any of the values.
        if ($matchMode instanceof NoneMatchMode) {
            $query->whereDoesntHave($scope, function (Builder $relQuery) use ($scope, $condition): void {
                $this->withinRelation($scope, fn () => $condition($relQuery, new AnyMatchMode));
            });

            return;
        }

        $query->whereHas($scope, function (Builder $relQuery) use ($scope, $condition): void {
            $this->withinRelation($scope, fn () => $condition($relQuery));
        });
    }

    /**
     * The relation a via() filter applies through, unless the query already filters
     * the records of that relation (inside a group bound to it).
     */
    protected function ownRelation(?Filter $filter): ?string
    {
        $relation = $filter?->getRelation();

        return $relation !== null && $relation === $this->currentRelation ? null : $relation;
    }

    /**
     * A relation group calls the relation by name on the model, so only relations
     * that a registered filter declares are allowed.
     */
    protected function guardRelation(string $relation): void
    {
        foreach ($this->filters as $filter) {
            if ($filter->belongsToRelation($relation)) {
                return;
            }
        }

        throw new InvalidArgumentException("Relation '{$relation}' is not declared by any registered filter");
    }

    /**
     * @param  callable(): void  $callback
     */
    protected function withinRelation(string $relation, callable $callback): void
    {
        $outer = $this->currentRelation;
        $this->currentRelation = $relation;
        try {
            $callback();
        } finally {
            $this->currentRelation = $outer;
        }
    }

    /**
     * Get the modified query.
     *
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder
     */
    public function getQuery(): Builder|QueryBuilder
    {
        return $this->query;
    }

    /**
     * Get the applied filters.
     *
     * @return array<FilterValue>
     */
    public function getAppliedFilters(): array
    {
        return $this->appliedFilters;
    }

    /**
     * Check if any filters have been applied.
     */
    public function hasAppliedFilters(): bool
    {
        return $this->appliedFilters !== [];
    }
}
