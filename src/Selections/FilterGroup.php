<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Selections;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Filters\Filter;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Represents a group of filter conditions with an operator (AND/OR).
 *
 * FilterGroups can contain:
 * - FilterValue items (leaf conditions)
 * - Nested FilterGroup items (for complex logic)
 *
 * @implements Arrayable<string, mixed>
 */
final class FilterGroup implements Arrayable, JsonSerializable
{
    /** @var array<FilterValue|FilterGroup> */
    protected array $items = [];

    public function __construct(
        protected GroupOperatorEnum $operator = GroupOperatorEnum::AND
    ) {}

    /**
     * Create an AND group.
     */
    public static function and(): self
    {
        return new self(GroupOperatorEnum::AND);
    }

    /**
     * Create an OR group.
     */
    public static function or(): self
    {
        return new self(GroupOperatorEnum::OR);
    }

    /**
     * Get the group operator.
     */
    public function getOperator(): GroupOperatorEnum
    {
        return $this->operator;
    }

    /**
     * Get all items in this group.
     *
     * @return array<FilterValue|FilterGroup>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Check if the group is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Get the number of items in this group.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Add a FilterValue to this group.
     */
    public function add(FilterValue $filterValue): self
    {
        $this->items[] = $filterValue;

        return $this;
    }

    /**
     * Add a nested FilterGroup to this group.
     */
    public function addGroup(FilterGroup $group): self
    {
        $this->items[] = $group;

        return $this;
    }

    /**
     * Start building a filter value for a filter class (AND context).
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function where(string $filterClass): FilterGroupBuilder
    {
        return new FilterGroupBuilder($filterClass, $this);
    }

    /**
     * Add an OR group with a callback.
     *
     * @example
     * $group->orWhere(function($orGroup) {
     *     $orGroup->where(StatusFilter::class)->is('pending');
     *     $orGroup->where(StatusFilter::class)->is('draft');
     * });
     */
    public function orWhere(callable $callback): self
    {
        $orGroup = self::or();
        $callback($orGroup);

        if (! $orGroup->isEmpty()) {
            $this->items[] = $orGroup;
        }

        return $this;
    }

    /**
     * Add an AND group with a callback.
     *
     * @example
     * $group->andWhere(function($andGroup) {
     *     $andGroup->where(StatusFilter::class)->is('active');
     *     $andGroup->where(CountFilter::class)->greaterThan(5);
     * });
     */
    public function andWhere(callable $callback): self
    {
        $andGroup = self::and();
        $callback($andGroup);

        if (! $andGroup->isEmpty()) {
            $this->items[] = $andGroup;
        }

        return $this;
    }

    /**
     * Get all FilterValues from this group (flattened, ignores nesting).
     *
     * @return array<FilterValue>
     */
    public function getAllFilterValues(): array
    {
        $values = [];

        foreach ($this->items as $item) {
            if ($item instanceof FilterValue) {
                $values[] = $item;
            } elseif ($item instanceof FilterGroup) {
                $values = array_merge($values, $item->getAllFilterValues());
            }
        }

        return $values;
    }

    /**
     * Get all unique filter keys from this group.
     *
     * @return array<string>
     */
    public function getAllFilterKeys(): array
    {
        return array_unique(array_map(
            fn (FilterValue $fv) => $fv->getFilterKey(),
            $this->getAllFilterValues()
        ));
    }

    /**
     * Check if this group contains nested groups.
     */
    public function hasNestedGroups(): bool
    {
        foreach ($this->items as $item) {
            if ($item instanceof FilterGroup) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert to array.
     *
     * @return array{operator: string, items: array<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'operator' => $this->operator->value,
            'items' => array_map(
                fn (FilterValue|FilterGroup $item) => $item->toArray(),
                $this->items
            ),
        ];
    }

    /**
     * @return array{operator: string, items: array<array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a FilterGroup from an array.
     *
     * @param  array{operator?: string, items?: array<array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        $operator = isset($data['operator'])
            ? GroupOperatorEnum::from($data['operator'])
            : GroupOperatorEnum::AND;

        $group = new self($operator);

        foreach ($data['items'] ?? [] as $itemData) {
            // Check if this is a nested group (has 'operator' key) or a filter value
            if (isset($itemData['operator'])) {
                $group->addGroup(self::fromArray($itemData));
            } else {
                $group->add(FilterValue::fromArray($itemData));
            }
        }

        return $group;
    }
}
