<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Selections;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Data\FilterValueBuilder;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Filters\Filter;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Represents a collection of filter conditions with support for AND/OR logic.
 *
 * FilterSelection uses a root FilterGroup internally to support complex
 * nested conditions while maintaining backward compatibility with the
 * simple array-based API.
 *
 * @implements Arrayable<string, mixed>
 */
final class FilterSelection implements Arrayable, Jsonable, JsonSerializable
{
    protected FilterGroup $rootGroup;

    protected ?string $name = null;

    protected ?string $description = null;

    public function __construct()
    {
        $this->rootGroup = FilterGroup::and();
    }

    /**
     * Create a new FilterSelection.
     */
    public static function make(?string $name = null): self
    {
        $selection = new self;
        $selection->name = $name;

        return $selection;
    }

    /**
     * Create a FilterSelection from JSON string.
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true);

        return self::fromArray($data);
    }

    /**
     * Create a FilterSelection from array.
     *
     * Supports both legacy format (flat 'filters' array) and new format (with 'group').
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $selection = new self;
        $selection->name = $data['name'] ?? null;
        $selection->description = $data['description'] ?? null;

        // Support new group-based format
        if (isset($data['group'])) {
            $selection->rootGroup = FilterGroup::fromArray($data['group']);
        }
        // Legacy format: flat 'filters' array (implicitly AND)
        elseif (isset($data['filters'])) {
            foreach ($data['filters'] as $filterData) {
                // Check if this is a nested group or a filter value
                if (isset($filterData['operator'])) {
                    $selection->rootGroup->addGroup(FilterGroup::fromArray($filterData));
                } else {
                    $selection->rootGroup->add(FilterValue::fromArray($filterData));
                }
            }
        }

        return $selection;
    }

    /**
     * Set the selection name.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the selection description.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Add a filter value to the root group.
     */
    public function add(FilterValue $filterValue): self
    {
        $this->rootGroup->add($filterValue);

        return $this;
    }

    /**
     * Add a nested FilterGroup.
     */
    public function addGroup(FilterGroup $group): self
    {
        $this->rootGroup->addGroup($group);

        return $this;
    }

    /**
     * Start building a filter value for a filter class.
     *
     * @param  class-string<Filter>  $filterClass
     *
     * @example
     * $selection->where(StatusFilter::class)->is('active');
     */
    public function where(string $filterClass): FilterValueBuilder
    {
        return new FilterValueBuilder($filterClass, $this);
    }

    /**
     * Add an OR group with conditions.
     *
     * @example
     * $selection
     *     ->where(StatusFilter::class)->is('active')
     *     ->orWhere(function($group) {
     *         $group->where(StatusFilter::class)->is('pending');
     *         $group->where(CountFilter::class)->greaterThan(10);
     *     });
     * // SQL: status = 'active' OR (status = 'pending' AND count > 10)
     */
    public function orWhere(callable $callback): self
    {
        $orGroup = FilterGroup::or();
        $callback($orGroup);

        if (! $orGroup->isEmpty()) {
            $this->rootGroup->addGroup($orGroup);
        }

        return $this;
    }

    /**
     * Add an AND group with conditions.
     *
     * @example
     * $selection
     *     ->andWhere(function($group) {
     *         $group->where(StatusFilter::class)->is('active');
     *         $group->where(CountFilter::class)->greaterThan(5);
     *     });
     */
    public function andWhere(callable $callback): self
    {
        $andGroup = FilterGroup::and();
        $callback($andGroup);

        if (! $andGroup->isEmpty()) {
            $this->rootGroup->addGroup($andGroup);
        }

        return $this;
    }

    /**
     * Create an OR selection (top-level OR instead of AND).
     *
     * @example
     * $selection = FilterSelection::makeOr()
     *     ->where(StatusFilter::class)->is('active')
     *     ->where(StatusFilter::class)->is('pending');
     * // SQL: status = 'active' OR status = 'pending'
     */
    public static function makeOr(?string $name = null): self
    {
        $selection = new self;
        $selection->name = $name;
        $selection->rootGroup = FilterGroup::or();

        return $selection;
    }

    /**
     * Add a filter value (called by FilterValueBuilder).
     *
     * @internal
     */
    public function addFromBuilder(FilterValue $filterValue): self
    {
        return $this->add($filterValue);
    }

    /**
     * Remove all filters for a specific filter class.
     *
     * Note: This only removes from the root group, not nested groups.
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function remove(string $filterClass): self
    {
        $key = $filterClass::key();
        $items = $this->rootGroup->getItems();

        // Rebuild root group without matching filters
        $newGroup = new FilterGroup($this->rootGroup->getOperator());
        foreach ($items as $item) {
            if ($item instanceof FilterValue && $item->getFilterKey() === $key) {
                continue;
            }
            if ($item instanceof FilterValue) {
                $newGroup->add($item);
            } else {
                $newGroup->addGroup($item);
            }
        }

        $this->rootGroup = $newGroup;

        return $this;
    }

    /**
     * Clear all filters.
     */
    public function clear(): self
    {
        $this->rootGroup = new FilterGroup($this->rootGroup->getOperator());

        return $this;
    }

    /**
     * Get all filter values (flattened from all groups).
     *
     * @return array<FilterValue>
     */
    public function all(): array
    {
        return $this->rootGroup->getAllFilterValues();
    }

    /**
     * Get the root filter group.
     */
    public function getGroup(): FilterGroup
    {
        return $this->rootGroup;
    }

    /**
     * Get the root operator.
     */
    public function getOperator(): GroupOperatorEnum
    {
        return $this->rootGroup->getOperator();
    }

    /**
     * Check if selection has nested groups (complex logic).
     */
    public function hasNestedGroups(): bool
    {
        return $this->rootGroup->hasNestedGroups();
    }

    /**
     * Get the selection name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the selection description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Check if selection has any filters.
     */
    public function hasFilters(): bool
    {
        return ! $this->rootGroup->isEmpty();
    }

    /**
     * Check if selection has a filter for a specific filter class.
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function has(string $filterClass): bool
    {
        $key = $filterClass::key();

        foreach ($this->all() as $filter) {
            if ($filter->getFilterKey() === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first filter value for a specific filter class.
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function get(string $filterClass): ?FilterValue
    {
        $key = $filterClass::key();

        foreach ($this->all() as $filter) {
            if ($filter->getFilterKey() === $key) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * Get filter count (total filter values across all groups).
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Convert to array.
     *
     * Uses new format with 'group' for complex selections,
     * falls back to legacy 'filters' format for simple AND selections.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // For backward compatibility, use legacy format if possible
        if (! $this->hasNestedGroups() && $this->rootGroup->getOperator() === GroupOperatorEnum::AND) {
            return [
                'name' => $this->name,
                'description' => $this->description,
                'filters' => array_map(fn (FilterValue $fv) => $fv->toArray(), $this->all()),
            ];
        }

        // Use new group-based format for complex selections
        return [
            'name' => $this->name,
            'description' => $this->description,
            'group' => $this->rootGroup->toArray(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
