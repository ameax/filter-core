<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters\Dynamic;

use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\DecimalFilter;

/**
 * Dynamic DECIMAL type filter with runtime-configurable properties.
 */
final class DynamicDecimalFilter extends DecimalFilter implements DynamicFilter
{
    protected string $key;

    protected string $columnName;

    protected string $labelText;

    protected bool $isNullable = false;

    protected int $decimalPrecision = 2;

    protected bool $isStoredAsInteger = false;

    protected ?float $minValue = null;

    protected ?float $maxValue = null;

    /** @var array<string, mixed> */
    protected array $metaData = [];

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->columnName = $key;
        $this->labelText = $key;
    }

    /**
     * Create a dynamic decimal filter.
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
        return FilterTypeEnum::DECIMAL;
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

    public function precision(): int
    {
        return $this->decimalPrecision;
    }

    public function storedAsInteger(): bool
    {
        return $this->isStoredAsInteger;
    }

    public function min(): ?float
    {
        return $this->minValue;
    }

    public function max(): ?float
    {
        return $this->maxValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->metaData;
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
     * Set the decimal precision (number of decimal places).
     */
    public function withPrecision(int $precision): self
    {
        $this->decimalPrecision = $precision;

        return $this;
    }

    /**
     * Set whether the value is stored as integer in the database.
     * E.g., 19.99 stored as 1999 (cents).
     */
    public function withStoredAsInteger(bool $storedAsInteger = true): self
    {
        $this->isStoredAsInteger = $storedAsInteger;

        return $this;
    }

    /**
     * Set the minimum allowed value.
     */
    public function withMin(float $min): self
    {
        $this->minValue = $min;

        return $this;
    }

    /**
     * Set the maximum allowed value.
     */
    public function withMax(float $max): self
    {
        $this->maxValue = $max;

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
