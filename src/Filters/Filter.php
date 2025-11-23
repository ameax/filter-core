<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicFilter;
use Ameax\FilterCore\MatchModes\EmptyMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\NotEmptyMatchMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
     * Apply custom filter logic to the query.
     *
     * Override this method to implement custom query logic for this filter.
     * Return true if custom logic was applied, false to use default QueryApplicator logic.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @return bool True if custom logic was applied, false to use default behavior
     */
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        return false;
    }

    /**
     * Sanitize/transform the value before validation and application.
     *
     * Override this method to normalize or convert input values.
     *
     * @example Convert string "true"/"false" to boolean
     * @example Trim whitespace from text input
     * @example Convert string numbers to integers
     */
    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        return $value;
    }

    /**
     * Laravel validation rules for the filter value.
     *
     * Override this method to define validation rules for the filter value.
     * The value will be validated as ['value' => $value] against these rules.
     *
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        return [];
    }

    /**
     * The default match mode for this filter.
     */
    public function defaultMode(): MatchModeContract
    {
        return new IsMatchMode();
    }

    /**
     * Allowed match modes for this filter.
     *
     * @return array<MatchModeContract>
     */
    abstract public function allowedModes(): array;

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
     * Start building a FilterValue for this filter.
     *
     * @return \Ameax\FilterCore\Data\FilterValueBuilder
     *
     * @example KoiStatusFilter::value()->is('active')
     * @example KoiCountFilter::value()->greaterThan(10)
     */
    public static function value(): \Ameax\FilterCore\Data\FilterValueBuilder
    {
        return \Ameax\FilterCore\Data\FilterValue::for(static::class);
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
     * Get the key for this filter instance (handles both static and dynamic filters).
     */
    public function resolveKey(): string
    {
        if ($this instanceof DynamicFilter) {
            return $this->getKey();
        }

        return static::key();
    }

    /**
     * Convert to FilterDefinition.
     */
    public function toDefinition(): FilterDefinition
    {
        $allowedModes = $this->allowedModes();

        if ($this->nullable()) {
            $allowedModes[] = new EmptyMatchMode();
            $allowedModes[] = new NotEmptyMatchMode();
        }

        return new FilterDefinition(
            key: $this->resolveKey(),
            type: $this->type(),
            column: $this->column(),
            label: $this->label(),
            allowedMatchModes: $allowedModes,
            defaultMatchMode: $this->defaultMode(),
            nullable: $this->nullable(),
            options: $this instanceof HasOptions ? $this->options() : [],
            meta: $this->meta(),
            relation: $this->getRelation(),
        );
    }
}
