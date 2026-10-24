<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Data;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\MatchModes\UnresolvedMatchMode;
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
        protected MatchModeContract $matchMode,
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

    public static function make(string $filterKey, MatchModeContract $matchMode, mixed $value): self
    {
        return new self($filterKey, $matchMode, $value);
    }

    public function getFilterKey(): string
    {
        return $this->filterKey;
    }

    public function getMatchMode(): MatchModeContract
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

    public function withMatchMode(MatchModeContract $matchMode): self
    {
        return new self($this->filterKey, $matchMode, $this->value);
    }

    public function toArray(): array
    {
        return [
            'filter' => $this->filterKey,
            'mode' => $this->matchMode->key(),
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
     * @param  bool  $lenient  keep an unknown match mode as UnresolvedMatchMode instead of
     *                         throwing, so the condition survives loading and saving and
     *                         the Resolver can report it
     */
    public static function fromArray(array $data, bool $lenient = false): self
    {
        $modeKey = (string) ($data['mode'] ?? '');

        if ($lenient && ! MatchMode::has($modeKey)) {
            $mode = new UnresolvedMatchMode($modeKey);
        } else {
            $mode = MatchMode::get($modeKey);
        }

        return new self(
            (string) ($data['filter'] ?? ''),
            $mode,
            $data['value'] ?? null,
        );
    }
}
