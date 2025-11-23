<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Query;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Enums\RelationModeEnum;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use TypeError;

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
        $relation = $filter?->getRelation();

        // Sanitize, type-check, and validate value if filter instance is available
        if ($filter !== null) {
            $value = $filter->sanitizeValue($value, $matchMode);
            $value = $this->applyTypedValue($filter, $filterKey, $value);
            $this->validateFilterValue($filter, $filterKey, $matchMode, $value);
        }

        // Convert BetweenValue to array for query application
        if ($value instanceof BetweenValue) {
            $value = $value->toArray();
        }

        // First, check if filter has custom apply logic
        if ($filter !== null && $filter->apply($this->query, $matchMode, $value)) {
            // Custom logic was applied - done
        } elseif ($relation !== null && $this->query instanceof Builder) {
            // Apply via relation with appropriate mode (whereHas, whereDoesntHave)
            $this->applyRelationFilter(
                $this->query,
                $relation,
                $filter->getRelationMode(),
                $column,
                $matchMode,
                $value
            );
        } else {
            // Apply match mode logic directly
            $matchMode->apply($this->query, $column, $value);
        }

        $this->appliedFilters[] = $filterValue;

        return $this;
    }

    /**
     * Apply the filter's typedValue() method if it exists.
     *
     * This provides strict type checking - if the filter defines a typedValue() method
     * with a specific type signature, PHP will throw a TypeError if the value doesn't match.
     *
     * @throws FilterValidationException When type check fails
     */
    protected function applyTypedValue(Filter $filter, string $filterKey, mixed $value): mixed
    {
        if (! method_exists($filter, 'typedValue')) {
            return $value;
        }

        try {
            return $filter->typedValue($value);
        } catch (TypeError $e) {
            throw new FilterValidationException(
                $filterKey,
                ['value' => ['The value type is invalid for this filter: '.$e->getMessage()]]
            );
        }
    }

    /**
     * Validate a filter value using the filter's validation rules.
     *
     * @throws FilterValidationException When validation fails
     */
    protected function validateFilterValue(
        Filter $filter,
        string $filterKey,
        MatchModeContract $matchMode,
        mixed $value
    ): void {
        $rules = $filter->validationRules($matchMode);

        if (empty($rules)) {
            return;
        }

        $validator = Validator::make(['value' => $value], $rules);

        if ($validator->fails()) {
            throw new FilterValidationException($filterKey, $validator->errors()->toArray());
        }
    }

    /**
     * Apply multiple filter values to the query.
     *
     * @param  array<FilterValue>  $filterValues
     */
    public function applyFilters(array $filterValues): self
    {
        foreach ($filterValues as $filterValue) {
            $this->applyFilter($filterValue);
        }

        return $this;
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

        $this->applyGroup($this->query, $selection->getGroup());

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
        $relation = $filter?->getRelation();

        if ($filter !== null) {
            $value = $filter->sanitizeValue($value, $matchMode);
            $value = $this->applyTypedValue($filter, $filterKey, $value);
            $this->validateFilterValue($filter, $filterKey, $matchMode, $value);
        }

        if ($value instanceof BetweenValue) {
            $value = $value->toArray();
        }

        // Build the condition closure
        $condition = function (Builder|QueryBuilder $q) use ($filter, $matchMode, $column, $value, $relation): void {
            if ($filter !== null && $filter->apply($q, $matchMode, $value)) {
                // Custom logic was applied
            } elseif ($relation !== null && $q instanceof Builder) {
                $this->applyRelationFilter(
                    $q,
                    $relation,
                    $filter->getRelationMode(),
                    $column,
                    $matchMode,
                    $value
                );
            } else {
                $matchMode->apply($q, $column, $value);
            }
        };

        // Apply with AND or OR
        if ($useOr) {
            $query->orWhere(fn ($q) => $condition($q));
        } else {
            $query->where(fn ($q) => $condition($q));
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
            $this->applyGroup($q, $group);
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
