<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Data;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\Selections\FilterSelection;

/**
 * Fluent builder for creating FilterValue instances.
 *
 * @example FilterValue::for(StatusFilter::class)->value('active')
 * @example FilterValue::for(StatusFilter::class)->is('active')
 * @example FilterValue::for(StatusFilter::class)->any(['a', 'b'])
 */
final class FilterValueBuilder
{
    protected ?MatchModeContract $mode = null;

    /**
     * @param  class-string<Filter>  $filterClass
     */
    public function __construct(
        protected string $filterClass,
        protected ?FilterSelection $selection = null,
    ) {}

    /**
     * Set the match mode.
     */
    public function mode(MatchModeContract $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Build the FilterValue with the given value.
     * Uses the filter's default mode if not explicitly set.
     *
     * Returns FilterSelection if called from a selection context, otherwise FilterValue.
     */
    public function value(mixed $value): FilterValue|FilterSelection
    {
        $mode = $this->mode ?? $this->getDefaultMode();

        $filterValue = new FilterValue(
            $this->filterClass::key(),
            $mode,
            $value,
        );

        if ($this->selection !== null) {
            $this->selection->addFromBuilder($filterValue);

            return $this->selection;
        }

        return $filterValue;
    }

    // Shorthand methods for common match modes

    /**
     * IS match mode.
     */
    public function is(mixed $value): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::is())->value($value);
    }

    /**
     * IS_NOT match mode.
     */
    public function isNot(mixed $value): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::isNot())->value($value);
    }

    /**
     * ANY match mode (value in array).
     *
     * @param  array<mixed>  $values
     */
    public function any(array $values): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::any())->value($values);
    }

    /**
     * NONE match mode (value not in array).
     *
     * @param  array<mixed>  $values
     */
    public function none(array $values): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::none())->value($values);
    }

    /**
     * GREATER_THAN match mode.
     */
    public function greaterThan(int|float $value): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::greaterThan())->value($value);
    }

    /**
     * LESS_THAN match mode.
     */
    public function lessThan(int|float $value): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::lessThan())->value($value);
    }

    /**
     * BETWEEN match mode.
     */
    public function between(int|float $min, int|float $max): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::between())->value(['min' => $min, 'max' => $max]);
    }

    /**
     * CONTAINS match mode.
     */
    public function contains(string $value): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::contains())->value($value);
    }

    /**
     * EMPTY match mode.
     */
    public function empty(): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::empty())->value(null);
    }

    /**
     * NOT_EMPTY match mode.
     */
    public function notEmpty(): FilterValue|FilterSelection
    {
        return $this->mode(MatchMode::notEmpty())->value(null);
    }

    /**
     * Get the default mode from the filter class.
     */
    protected function getDefaultMode(): MatchModeContract
    {
        /** @var Filter $filter */
        $filter = new ($this->filterClass)();

        return $filter->defaultMode();
    }
}
