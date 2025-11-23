<?php

namespace Ameax\FilterCore\Data;

use Ameax\FilterCore\Enums\MatchModeEnum;
use Ameax\FilterCore\Filters\Filter;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Represents a single filter value with its match mode.
 *
 * Example: "status IS active" or "count GREATER_THAN 5"
 *
 * @implements Arrayable<string, mixed>
 */
final class FilterValue implements Arrayable, JsonSerializable
{
    public function __construct(
        protected string $filterKey,
        protected MatchModeEnum $matchMode,
        protected mixed $value,
    ) {}

    /**
     * Create a fluent builder for a filter class.
     *
     * @param  class-string<Filter>  $filterClass
     *
     * @example FilterValue::for(StatusFilter::class)->value('active')
     * @example FilterValue::for(StatusFilter::class)->is('active')
     */
    public static function for(string $filterClass): FilterValueBuilder
    {
        return new FilterValueBuilder($filterClass);
    }

    public static function make(string $filterKey, MatchModeEnum $matchMode, mixed $value): self
    {
        return new self($filterKey, $matchMode, $value);
    }

    public function getFilterKey(): string
    {
        return $this->filterKey;
    }

    public function getMatchMode(): MatchModeEnum
    {
        return $this->matchMode;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function withValue(mixed $value): self
    {
        return new self($this->filterKey, $this->matchMode, $value);
    }

    public function withMatchMode(MatchModeEnum $matchMode): self
    {
        return new self($this->filterKey, $matchMode, $this->value);
    }

    public function toArray(): array
    {
        return [
            'filter' => $this->filterKey,
            'mode' => $this->matchMode->value,
            'value' => $this->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['filter'],
            MatchModeEnum::from($data['mode']),
            $data['value'],
        );
    }
}
