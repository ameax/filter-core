<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Collection;

use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Enums\RelationModeEnum;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\Query\ValuePreparer;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Applies filter values to Laravel Collections.
 */
final class CollectionApplicator
{
    /** @var array<string, Filter> */
    protected array $filters = [];

    /** @var array<string, FilterDefinition> */
    protected array $filterDefinitions = [];

    /** @var array<FilterValue> */
    protected array $appliedFilters = [];

    /**
     * @param  Collection<int|string, mixed>  $collection
     */
    public function __construct(
        protected Collection $collection,
    ) {}

    /**
     * Create a new CollectionApplicator for the given collection.
     *
     * @param  Collection<int|string, mixed>  $collection
     */
    public static function for(Collection $collection): self
    {
        return new self($collection);
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
     * Apply a single filter value to the collection.
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

        $filter = $this->filters[$filterKey] ?? null;

        // Sanitize, type-check, and validate value if filter instance is available
        if ($filter !== null) {
            $value = ValuePreparer::prepare($filter, $filterKey, $matchMode, $value);
        }

        // Convert BetweenValue to array for collection application
        if ($value instanceof BetweenValue) {
            $value = $value->toArray();
        }

        // Apply match mode logic to collection
        $this->collection = $matchMode->applyToCollection($this->collection, $column, $value);

        $this->appliedFilters[] = $filterValue;

        return $this;
    }

    /**
     * Apply multiple filter values to the collection.
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

    /** The relation whose items are being filtered (inside a relation group), if any. */
    protected ?string $currentRelation = null;

    /**
     * Apply a FilterSelection to the collection.
     *
     * This handles complex nested AND/OR logic.
     */
    public function applySelection(FilterSelection $selection): self
    {
        if (! $selection->hasFilters()) {
            return $this;
        }

        $this->collection = $this->applyGroup($this->collection, $selection->getGroup());

        return $this;
    }

    /**
     * Apply a FilterGroup to a collection (recursive).
     *
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     */
    public function applyGroup(Collection $collection, FilterGroup $group): Collection
    {
        if ($group->isEmpty()) {
            return $collection;
        }

        $relation = $group->getRelation();
        if ($relation !== null) {
            // Same semantics as the query: the group's conditions hold for one related item.
            return $collection->filter(function (mixed $item) use ($relation, $group): bool {
                $related = self::related($item, $relation);

                return match ($group->getRelationMode()) {
                    RelationModeEnum::HAS => $this->withinRelation($relation, fn () => $this->applyItems($related, $group))->isNotEmpty(),
                    RelationModeEnum::DOESNT_HAVE => $this->withinRelation($relation, fn () => $this->applyItems($related, $group))->isEmpty(),
                    RelationModeEnum::HAS_NONE => $related->isEmpty(),
                };
            });
        }

        return $this->applyItems($collection, $group);
    }

    /**
     * Apply the items of a group with its operator, ignoring a relation binding.
     *
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     */
    protected function applyItems(Collection $collection, FilterGroup $group): Collection
    {
        $items = $group->getItems();
        $isOr = $group->getOperator() === GroupOperatorEnum::OR;

        if ($isOr) {
            return $this->applyOrGroup($collection, $items);
        }

        return $this->applyAndGroup($collection, $items);
    }

    /**
     * Apply items with AND logic (all conditions must match).
     *
     * @param  Collection<int|string, mixed>  $collection
     * @param  array<FilterValue|FilterGroup>  $items
     * @return Collection<int|string, mixed>
     */
    protected function applyAndGroup(Collection $collection, array $items): Collection
    {
        $result = $collection;

        foreach ($items as $item) {
            if ($item instanceof FilterValue) {
                $result = $this->applyFilterValueToCollection($result, $item);
            } elseif ($item instanceof FilterGroup) {
                $result = $this->applyGroup($result, $item);
            }
        }

        return $result;
    }

    /**
     * Apply items with OR logic (any condition must match).
     *
     * @param  Collection<int|string, mixed>  $collection
     * @param  array<FilterValue|FilterGroup>  $items
     * @return Collection<int|string, mixed>
     */
    protected function applyOrGroup(Collection $collection, array $items): Collection
    {
        // For OR logic, we collect matched items from each condition
        // We can't use only() with keys because Eloquent collections use model IDs
        // instead of array indices for the only() method
        $matchedResults = [];

        foreach ($items as $item) {
            if ($item instanceof FilterValue) {
                $matchedResults[] = $this->applyFilterValueToCollection($collection, $item);
            } elseif ($item instanceof FilterGroup) {
                $matchedResults[] = $this->applyGroup($collection, $item);
            }
        }

        // Return items that matched any condition by checking if item exists in any result
        return $collection->filter(function (mixed $item) use ($matchedResults): bool {
            foreach ($matchedResults as $result) {
                if ($result->contains($item)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Apply a FilterValue to a collection.
     *
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     */
    protected function applyFilterValueToCollection(Collection $collection, FilterValue $filterValue): Collection
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

        $this->appliedFilters[] = $filterValue;

        $scope = $filter?->relationScope();
        if ($scope !== null && $scope !== $this->currentRelation) {
            return $collection->filter(fn (mixed $item): bool => $this->withinRelation(
                $scope,
                fn () => $matchMode->applyToCollection(self::related($item, $scope), $column, $value)
            )->isNotEmpty());
        }

        return $matchMode->applyToCollection($collection, $column, $value);
    }

    /**
     * The related items of an item, as a collection.
     *
     * @return Collection<int|string, mixed>
     */
    protected static function related(mixed $item, string $relation): Collection
    {
        $related = data_get($item, $relation);
        if ($related instanceof Collection) {
            return $related;
        }
        if ($related === null) {
            return new Collection;
        }

        return new Collection(is_iterable($related) ? $related : [$related]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withinRelation(string $relation, callable $callback): mixed
    {
        $outer = $this->currentRelation;
        $this->currentRelation = $relation;
        try {
            return $callback();
        } finally {
            $this->currentRelation = $outer;
        }
    }

    /**
     * Get the filtered collection.
     *
     * @return Collection<int|string, mixed>
     */
    public function getCollection(): Collection
    {
        return $this->collection;
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
