<?php

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;

/**
 * Base class for all filter definitions.
 */
abstract class Filter
{
    protected ?string $relation = null;

    /**
     * The database column this filter operates on.
     */
    abstract public function column(): string;

    /**
     * The filter type.
     */
    abstract public function type(): FilterTypeEnum;

    /**
     * The default match mode for this filter.
     */
    public function defaultMode(): MatchModeEnum
    {
        return MatchModeEnum::IS;
    }

    /**
     * Allowed match modes for this filter.
     *
     * @return array<MatchModeEnum>
     */
    public function allowedModes(): array
    {
        return $this->type()->defaultMatchModes();
    }

    /**
     * Human-readable label for the filter.
     */
    public function label(): string
    {
        return static::key();
    }

    /**
     * Whether the column is nullable.
     */
    public function nullable(): bool
    {
        return false;
    }

    /**
     * Additional metadata.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [];
    }

    /**
     * Get the unique key for this filter (class name without namespace).
     */
    public static function key(): string
    {
        return class_basename(static::class);
    }

    /**
     * Create an instance of this filter.
     *
     * @return static
     */
    public static function make(): self
    {
        // @phpstan-ignore new.static
        return new static();
    }

    /**
     * Create a filter that applies via a relation.
     *
     * @return static
     */
    public static function via(string $relation): self
    {
        // @phpstan-ignore new.static
        $filter = new static();
        $filter->relation = $relation;

        return $filter;
    }

    /**
     * Get the relation this filter applies through.
     */
    public function getRelation(): ?string
    {
        return $this->relation;
    }

    /**
     * Check if this filter applies via a relation.
     */
    public function hasRelation(): bool
    {
        return $this->relation !== null;
    }

    /**
     * Convert to FilterDefinition.
     */
    public function toDefinition(): FilterDefinition
    {
        $allowedModes = $this->allowedModes();

        if ($this->nullable()) {
            $allowedModes[] = MatchModeEnum::EMPTY;
            $allowedModes[] = MatchModeEnum::NOT_EMPTY;
        }

        return new FilterDefinition(
            key: static::key(),
            type: $this->type(),
            column: $this->column(),
            label: $this->label(),
            allowedMatchModes: $allowedModes,
            defaultMatchMode: $this->defaultMode(),
            nullable: $this->nullable(),
            options: $this instanceof HasOptions ? $this->options() : [],
            meta: $this->meta(),
        );
    }
}
