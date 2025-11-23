<?php

namespace Ameax\FilterCore\Query;

use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\MatchModeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Applies filter values to Eloquent queries.
 */
final class QueryApplicator
{
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
     * Register filter definitions.
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

        $this->applyMatchMode($column, $matchMode, $value);

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
     * Apply the match mode logic to the query.
     */
    protected function applyMatchMode(string $column, MatchModeEnum $matchMode, mixed $value): void
    {
        match ($matchMode) {
            MatchModeEnum::IS => $this->applyIs($column, $value),
            MatchModeEnum::IS_NOT => $this->applyIsNot($column, $value),
            MatchModeEnum::ANY => $this->applyAny($column, $value),
            MatchModeEnum::NONE => $this->applyNone($column, $value),
            MatchModeEnum::GREATER_THAN => $this->query->where($column, '>', $value),
            MatchModeEnum::LESS_THAN => $this->query->where($column, '<', $value),
            MatchModeEnum::BETWEEN => $this->applyBetween($column, $value),
            MatchModeEnum::CONTAINS => $this->query->where($column, 'like', '%'.$value.'%'),
            MatchModeEnum::EMPTY => $this->query->whereNull($column),
            MatchModeEnum::NOT_EMPTY => $this->query->whereNotNull($column),
        };
    }

    /**
     * Apply IS match mode.
     * Single value: WHERE column = value
     * Array value: WHERE column IN (values)
     */
    protected function applyIs(string $column, mixed $value): void
    {
        if (is_array($value)) {
            $this->query->whereIn($column, $value);
        } else {
            $this->query->where($column, '=', $value);
        }
    }

    /**
     * Apply IS_NOT match mode.
     * Single value: WHERE column != value
     * Array value: WHERE column NOT IN (values)
     */
    protected function applyIsNot(string $column, mixed $value): void
    {
        if (is_array($value)) {
            $this->query->whereNotIn($column, $value);
        } else {
            $this->query->where($column, '!=', $value);
        }
    }

    /**
     * Apply ANY match mode (value is in array).
     */
    protected function applyAny(string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $this->query->whereIn($column, $values);
    }

    /**
     * Apply NONE match mode (value is not in array).
     */
    protected function applyNone(string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $this->query->whereNotIn($column, $values);
    }

    /**
     * Apply BETWEEN match mode.
     * Expects an array with [min, max] or ['min' => value, 'max' => value].
     */
    protected function applyBetween(string $column, mixed $value): void
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('BETWEEN match mode requires an array value');
        }

        $min = $value['min'] ?? $value[0] ?? null;
        $max = $value['max'] ?? $value[1] ?? null;

        if ($min === null || $max === null) {
            throw new InvalidArgumentException('BETWEEN match mode requires both min and max values');
        }

        $this->query->whereBetween($column, [$min, $max]);
    }
}
