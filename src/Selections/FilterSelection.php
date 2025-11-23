<?php

namespace Ameax\FilterCore\Selections;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Data\FilterValueBuilder;
use Ameax\FilterCore\Filters\Filter;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Represents a collection of filter values (AND logic).
 *
 * @implements Arrayable<int, array<string, mixed>>
 */
final class FilterSelection implements Arrayable, Jsonable, JsonSerializable
{
    /** @var array<FilterValue> */
    protected array $filters = [];

    protected ?string $name = null;

    protected ?string $description = null;

    public function __construct() {}

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
        /** @var array{name?: string, description?: string, filters: array<array<string, mixed>>} $data */
        $data = json_decode($json, true);

        return self::fromArray($data);
    }

    /**
     * Create a FilterSelection from array.
     *
     * @param  array{name?: string, description?: string, filters: array<array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        $selection = new self;
        $selection->name = $data['name'] ?? null;
        $selection->description = $data['description'] ?? null;

        foreach ($data['filters'] as $filterData) {
            $selection->filters[] = FilterValue::fromArray($filterData);
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
     * Add a filter value.
     */
    public function add(FilterValue $filterValue): self
    {
        $this->filters[] = $filterValue;

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
     * @param  class-string<Filter>  $filterClass
     */
    public function remove(string $filterClass): self
    {
        $key = $filterClass::key();
        $this->filters = array_values(array_filter(
            $this->filters,
            fn (FilterValue $fv) => $fv->getFilterKey() !== $key
        ));

        return $this;
    }

    /**
     * Clear all filters.
     */
    public function clear(): self
    {
        $this->filters = [];

        return $this;
    }

    /**
     * Get all filter values.
     *
     * @return array<FilterValue>
     */
    public function all(): array
    {
        return $this->filters;
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
        return $this->filters !== [];
    }

    /**
     * Check if selection has a filter for a specific filter class.
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function has(string $filterClass): bool
    {
        $key = $filterClass::key();

        foreach ($this->filters as $filter) {
            if ($filter->getFilterKey() === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the filter value for a specific filter class.
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function get(string $filterClass): ?FilterValue
    {
        $key = $filterClass::key();

        foreach ($this->filters as $filter) {
            if ($filter->getFilterKey() === $key) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * Get filter count.
     */
    public function count(): int
    {
        return count($this->filters);
    }

    /**
     * Convert to array.
     *
     * @return array{name: string|null, description: string|null, filters: array<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'filters' => array_map(fn (FilterValue $fv) => $fv->toArray(), $this->filters),
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
     * @return array{name: string|null, description: string|null, filters: array<array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
