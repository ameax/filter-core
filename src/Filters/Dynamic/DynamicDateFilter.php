<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters\Dynamic;

use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\DateFilter;

/**
 * Dynamic DATE type filter with runtime-configurable properties.
 */
final class DynamicDateFilter extends DateFilter implements DynamicFilter
{
    protected string $key;

    protected string $columnName;

    protected string $labelText;

    protected bool $isNullable = false;

    /** @var array<DateDirection>|null */
    protected ?array $directions = null;

    protected bool $includeToday = true;

    protected bool $hasTimeColumn = false;

    /** @var array<string, mixed> */
    protected array $metaData = [];

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->columnName = $key;
        $this->labelText = $key;
    }

    /**
     * Create a dynamic date filter.
     */
    public static function create(string $key): self
    {
        return new self($key);
    }

    public static function key(): string
    {
        throw new \BadMethodCallException('Use instance method getKey() for dynamic filters');
    }

    /**
     * Get the unique key for this filter instance.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATE;
    }

    public function column(): string
    {
        return $this->columnName;
    }

    public function label(): string
    {
        return $this->labelText;
    }

    public function nullable(): bool
    {
        return $this->isNullable;
    }

    /**
     * @return array<DateDirection>|null
     */
    public function allowedDirections(): ?array
    {
        return $this->directions;
    }

    public function allowToday(): bool
    {
        return $this->includeToday;
    }

    public function hasTime(): bool
    {
        return $this->hasTimeColumn;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return array_merge(parent::meta(), $this->metaData);
    }

    // Fluent setters

    /**
     * Set the database column name.
     */
    public function withColumn(string $column): self
    {
        $this->columnName = $column;

        return $this;
    }

    /**
     * Set the human-readable label.
     */
    public function withLabel(string $label): self
    {
        $this->labelText = $label;

        return $this;
    }

    /**
     * Set the relation this filter applies through.
     */
    public function withRelation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * Set whether the column is nullable.
     */
    public function withNullable(bool $nullable = true): self
    {
        $this->isNullable = $nullable;

        return $this;
    }

    /**
     * Set allowed directions for date selection.
     *
     * @param  array<DateDirection>|null  $directions
     */
    public function withAllowedDirections(?array $directions): self
    {
        $this->directions = $directions;

        return $this;
    }

    /**
     * Restrict to past dates only.
     */
    public function withPastOnly(): self
    {
        $this->directions = [DateDirection::PAST];

        return $this;
    }

    /**
     * Restrict to future dates only.
     */
    public function withFutureOnly(): self
    {
        $this->directions = [DateDirection::FUTURE];

        return $this;
    }

    /**
     * Set whether to include "today" option even when future-only.
     */
    public function withAllowToday(bool $allow = true): self
    {
        $this->includeToday = $allow;

        return $this;
    }

    /**
     * Set whether the column has time (DATETIME/TIMESTAMP).
     *
     * When true, timezone conversion is applied to queries:
     * User's "today" in Europe/Berlin becomes UTC range in the query.
     */
    public function withTime(bool $hasTime = true): self
    {
        $this->hasTimeColumn = $hasTime;

        return $this;
    }

    /**
     * Set additional metadata.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $this->metaData = $meta;

        return $this;
    }
}
