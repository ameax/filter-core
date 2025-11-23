<?php

namespace Ameax\FilterCore\Data;

use Ameax\FilterCore\Enums\MatchModeEnum;
use Ameax\FilterCore\Filters\Filter;

/**
 * Fluent builder for creating FilterValue instances.
 *
 * @example FilterValue::for(StatusFilter::class)->value('active')
 * @example FilterValue::for(StatusFilter::class)->is('active')
 * @example FilterValue::for(StatusFilter::class)->any(['a', 'b'])
 */
final class FilterValueBuilder
{
    protected ?MatchModeEnum $mode = null;

    /**
     * @param  class-string<Filter>  $filterClass
     */
    public function __construct(
        protected string $filterClass,
    ) {}

    /**
     * Set the match mode.
     */
    public function mode(MatchModeEnum $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Build the FilterValue with the given value.
     * Uses the filter's default mode if not explicitly set.
     */
    public function value(mixed $value): FilterValue
    {
        $mode = $this->mode ?? $this->getDefaultMode();

        return new FilterValue(
            $this->filterClass::key(),
            $mode,
            $value,
        );
    }

    // Shorthand methods for common match modes

    /**
     * IS match mode.
     */
    public function is(mixed $value): FilterValue
    {
        return $this->mode(MatchModeEnum::IS)->value($value);
    }

    /**
     * IS_NOT match mode.
     */
    public function isNot(mixed $value): FilterValue
    {
        return $this->mode(MatchModeEnum::IS_NOT)->value($value);
    }

    /**
     * ANY match mode (value in array).
     *
     * @param  array<mixed>  $values
     */
    public function any(array $values): FilterValue
    {
        return $this->mode(MatchModeEnum::ANY)->value($values);
    }

    /**
     * NONE match mode (value not in array).
     *
     * @param  array<mixed>  $values
     */
    public function none(array $values): FilterValue
    {
        return $this->mode(MatchModeEnum::NONE)->value($values);
    }

    /**
     * GREATER_THAN match mode.
     */
    public function greaterThan(int|float $value): FilterValue
    {
        return $this->mode(MatchModeEnum::GREATER_THAN)->value($value);
    }

    /**
     * LESS_THAN match mode.
     */
    public function lessThan(int|float $value): FilterValue
    {
        return $this->mode(MatchModeEnum::LESS_THAN)->value($value);
    }

    /**
     * BETWEEN match mode.
     */
    public function between(int|float $min, int|float $max): FilterValue
    {
        return $this->mode(MatchModeEnum::BETWEEN)->value(['min' => $min, 'max' => $max]);
    }

    /**
     * CONTAINS match mode.
     */
    public function contains(string $value): FilterValue
    {
        return $this->mode(MatchModeEnum::CONTAINS)->value($value);
    }

    /**
     * EMPTY match mode.
     */
    public function empty(): FilterValue
    {
        return $this->mode(MatchModeEnum::EMPTY)->value(null);
    }

    /**
     * NOT_EMPTY match mode.
     */
    public function notEmpty(): FilterValue
    {
        return $this->mode(MatchModeEnum::NOT_EMPTY)->value(null);
    }

    /**
     * Get the default mode from the filter class.
     */
    protected function getDefaultMode(): MatchModeEnum
    {
        /** @var Filter $filter */
        $filter = new ($this->filterClass)();

        return $filter->defaultMode();
    }
}
