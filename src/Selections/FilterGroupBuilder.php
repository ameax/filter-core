<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Selections;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\MatchModes\MatchMode;

/**
 * Fluent builder for adding FilterValue to a FilterGroup.
 *
 * @example $group->where(StatusFilter::class)->is('active')
 */
final class FilterGroupBuilder
{
    protected ?MatchModeContract $mode = null;

    /**
     * @param  class-string<Filter>  $filterClass
     */
    public function __construct(
        protected string $filterClass,
        protected FilterGroup $group,
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
     * Build the FilterValue with the given value and add to group.
     */
    public function value(mixed $value): FilterGroup
    {
        $mode = $this->mode ?? $this->getDefaultMode();

        $filterValue = new FilterValue(
            $this->filterClass::key(),
            $mode,
            $value,
        );

        $this->group->add($filterValue);

        return $this->group;
    }

    // Shorthand methods for common match modes

    /**
     * IS match mode.
     */
    public function is(mixed $value): FilterGroup
    {
        return $this->mode(MatchMode::is())->value($value);
    }

    /**
     * IS_NOT match mode.
     */
    public function isNot(mixed $value): FilterGroup
    {
        return $this->mode(MatchMode::isNot())->value($value);
    }

    /**
     * ANY match mode (value in array).
     *
     * @param  array<mixed>  $values
     */
    public function any(array $values): FilterGroup
    {
        return $this->mode(MatchMode::any())->value($values);
    }

    /**
     * NONE match mode (value not in array).
     *
     * @param  array<mixed>  $values
     */
    public function none(array $values): FilterGroup
    {
        return $this->mode(MatchMode::none())->value($values);
    }

    /**
     * GT (>) match mode.
     */
    public function gt(int|float $value): FilterGroup
    {
        return $this->mode(MatchMode::gt())->value($value);
    }

    /**
     * LT (<) match mode.
     */
    public function lt(int|float $value): FilterGroup
    {
        return $this->mode(MatchMode::lt())->value($value);
    }

    /**
     * BETWEEN match mode.
     */
    public function between(int|float $min, int|float $max): FilterGroup
    {
        return $this->mode(MatchMode::between())->value(['min' => $min, 'max' => $max]);
    }

    /**
     * CONTAINS match mode.
     */
    public function contains(string $value): FilterGroup
    {
        return $this->mode(MatchMode::contains())->value($value);
    }

    /**
     * EMPTY match mode.
     */
    public function empty(): FilterGroup
    {
        return $this->mode(MatchMode::empty())->value(null);
    }

    /**
     * NOT_EMPTY match mode.
     */
    public function notEmpty(): FilterGroup
    {
        return $this->mode(MatchMode::notEmpty())->value(null);
    }

    /**
     * ALL match mode (all values must match).
     *
     * @param  array<mixed>  $values
     */
    public function all(array $values): FilterGroup
    {
        return $this->mode(MatchMode::all())->value($values);
    }

    /**
     * GTE (>=) match mode.
     */
    public function gte(int|float $value): FilterGroup
    {
        return $this->mode(MatchMode::gte())->value($value);
    }

    /**
     * LTE (<=) match mode.
     */
    public function lte(int|float $value): FilterGroup
    {
        return $this->mode(MatchMode::lte())->value($value);
    }

    /**
     * STARTS_WITH match mode.
     */
    public function startsWith(string $value): FilterGroup
    {
        return $this->mode(MatchMode::startsWith())->value($value);
    }

    /**
     * ENDS_WITH match mode.
     */
    public function endsWith(string $value): FilterGroup
    {
        return $this->mode(MatchMode::endsWith())->value($value);
    }

    /**
     * REGEX match mode.
     */
    public function regex(string $pattern): FilterGroup
    {
        return $this->mode(MatchMode::regex())->value($pattern);
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
