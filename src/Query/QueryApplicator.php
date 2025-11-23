<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Query;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Filters\Filter;
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
            // Apply via whereHas for relation filters
            $this->query->whereHas($relation, function (Builder $query) use ($column, $matchMode, $value): void {
                $matchMode->apply($query, $column, $value);
            });
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
