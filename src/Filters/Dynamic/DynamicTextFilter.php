<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters\Dynamic;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\MatchModes\ContainsMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;

/**
 * Dynamic TEXT type filter with runtime-configurable properties.
 */
final class DynamicTextFilter extends Filter implements DynamicFilter
{
    protected string $key;

    protected string $columnName;

    protected string $labelText;

    protected bool $isNullable = false;

    /** @var array<string, mixed> */
    protected array $metaData = [];

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->columnName = $key;
        $this->labelText = $key;
    }

    /**
     * Create a dynamic text filter.
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
        return FilterTypeEnum::TEXT;
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
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->metaData;
    }

    public function defaultMode(): MatchModeContract
    {
        return new ContainsMatchMode();
    }

    /**
     * @return array<MatchModeContract>
     */
    public function allowedModes(): array
    {
        return [
            new ContainsMatchMode(),
            new IsMatchMode(),
            new IsNotMatchMode(),
        ];
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
