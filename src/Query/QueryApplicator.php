<?php

namespace Ameax\FilterCore\Query;

use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\MatchModeEnum;
use Ameax\FilterCore\Filters\Filter;
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
            $key = $filterInstance::key();

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
                "Match mode '{$matchMode->value}' is not allowed for filter '{$filterKey}'"
            );
        }

        $column = $definition->getColumn();
        $value = $filterValue->getValue();

        // Check if this filter has a relation
        $filter = $this->filters[$filterKey] ?? null;
        $relation = $filter?->getRelation();

        if ($relation !== null && $this->query instanceof Builder) {
            // Apply via whereHas
            $this->query->whereHas($relation, function (Builder $query) use ($column, $matchMode, $value): void {
                $this->applyMatchModeToQuery($query, $column, $matchMode, $value);
            });
        } else {
            // Apply directly
            $this->applyMatchModeToQuery($this->query, $column, $matchMode, $value);
        }

        $this->appliedFilters[] = $filterValue;

        return $this;
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

    /**
     * Apply the match mode logic to a query.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyMatchModeToQuery(
        Builder|QueryBuilder $query,
        string $column,
        MatchModeEnum $matchMode,
        mixed $value
    ): void {
        match ($matchMode) {
            MatchModeEnum::IS => $this->applyIs($query, $column, $value),
            MatchModeEnum::IS_NOT => $this->applyIsNot($query, $column, $value),
            MatchModeEnum::ANY => $this->applyAny($query, $column, $value),
            MatchModeEnum::NONE => $this->applyNone($query, $column, $value),
            MatchModeEnum::GREATER_THAN => $query->where($column, '>', $value),
            MatchModeEnum::LESS_THAN => $query->where($column, '<', $value),
            MatchModeEnum::BETWEEN => $this->applyBetween($query, $column, $value),
            MatchModeEnum::CONTAINS => $query->where($column, 'like', '%'.$value.'%'),
            MatchModeEnum::EMPTY => $query->whereNull($column),
            MatchModeEnum::NOT_EMPTY => $query->whereNotNull($column),
        };
    }

    /**
     * Apply IS match mode.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyIs(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereIn($column, $value);
        } else {
            $query->where($column, '=', $value);
        }
    }

    /**
     * Apply IS_NOT match mode.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyIsNot(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereNotIn($column, $value);
        } else {
            $query->where($column, '!=', $value);
        }
    }

    /**
     * Apply ANY match mode.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyAny(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn($column, $values);
    }

    /**
     * Apply NONE match mode.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyNone(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereNotIn($column, $values);
    }

    /**
     * Apply BETWEEN match mode.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyBetween(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('BETWEEN match mode requires an array value');
        }

        $min = $value['min'] ?? $value[0] ?? null;
        $max = $value['max'] ?? $value[1] ?? null;

        if ($min === null || $max === null) {
            throw new InvalidArgumentException('BETWEEN match mode requires both min and max values');
        }

        $query->whereBetween($column, [$min, $max]);
    }
}
