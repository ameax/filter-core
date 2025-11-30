<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Data;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\MatchModes\AllMatchMode;
use Ameax\FilterCore\MatchModes\AnyMatchMode;
use Ameax\FilterCore\MatchModes\BetweenMatchMode;
use Ameax\FilterCore\MatchModes\ContainsMatchMode;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\EmptyMatchMode;
use Ameax\FilterCore\MatchModes\EndsWithMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanOrEqualMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;
use Ameax\FilterCore\MatchModes\LessThanMatchMode;
use Ameax\FilterCore\MatchModes\LessThanOrEqualMatchMode;
use Ameax\FilterCore\MatchModes\NoneMatchMode;
use Ameax\FilterCore\MatchModes\NotEmptyMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Ameax\FilterCore\MatchModes\RegexMatchMode;
use Ameax\FilterCore\MatchModes\StartsWithMatchMode;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Defines a filter's metadata: key, label, type, column, and allowed match modes.
 *
 * @implements Arrayable<string, mixed>
 */
final class FilterDefinition implements Arrayable, JsonSerializable
{
    /**
     * @param  array<MatchModeContract>  $allowedMatchModes
     * @param  array<int|string, mixed>  $options  For SELECT type: ['value' => 'Label', ...]
     * @param  array<string, mixed>  $meta  Additional metadata
     * @param  string|null  $relation  Relation name for relation filters (e.g., 'pond')
     */
    public function __construct(
        protected string $key,
        protected FilterTypeEnum $type,
        protected string $column,
        protected ?string $label = null,
        protected array $allowedMatchModes = [],
        protected ?MatchModeContract $defaultMatchMode = null,
        protected bool $nullable = false,
        protected array $options = [],
        protected array $meta = [],
        protected ?string $relation = null,
    ) {
        if (empty($this->allowedMatchModes)) {
            $this->allowedMatchModes = $this->getDefaultMatchModesForType();
        }

        if ($this->defaultMatchMode === null) {
            $this->defaultMatchMode = $this->allowedMatchModes[0] ?? new IsMatchMode;
        }

        if ($this->label === null) {
            $this->label = $this->key;
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): FilterTypeEnum
    {
        return $this->type;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->key;
    }

    /**
     * @return array<MatchModeContract>
     */
    public function getAllowedMatchModes(): array
    {
        return $this->allowedMatchModes;
    }

    public function getDefaultMatchMode(): MatchModeContract
    {
        return $this->defaultMatchMode ?? new IsMatchMode;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Get the relation name for relation filters.
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

    public function isMatchModeAllowed(MatchModeContract $mode): bool
    {
        foreach ($this->allowedMatchModes as $allowedMode) {
            if ($allowedMode->key() === $mode->key()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<MatchModeContract>
     */
    protected function getDefaultMatchModesForType(): array
    {
        $modes = match ($this->type) {
            FilterTypeEnum::SELECT => [
                new IsMatchMode,
                new IsNotMatchMode,
                new AnyMatchMode,
                new AllMatchMode,
                new NoneMatchMode,
            ],
            FilterTypeEnum::INTEGER => [
                new IsMatchMode,
                new IsNotMatchMode,
                new GreaterThanMatchMode,
                new GreaterThanOrEqualMatchMode,
                new LessThanMatchMode,
                new LessThanOrEqualMatchMode,
                new BetweenMatchMode,
            ],
            FilterTypeEnum::TEXT => [
                new ContainsMatchMode,
                new StartsWithMatchMode,
                new EndsWithMatchMode,
                new RegexMatchMode,
                new IsMatchMode,
                new IsNotMatchMode,
            ],
            FilterTypeEnum::BOOLEAN => [
                new IsMatchMode,
            ],
            FilterTypeEnum::DECIMAL => [
                new IsMatchMode,
                new IsNotMatchMode,
                new AnyMatchMode,
                new NoneMatchMode,
                new GreaterThanMatchMode,
                new GreaterThanOrEqualMatchMode,
                new LessThanMatchMode,
                new LessThanOrEqualMatchMode,
                new BetweenMatchMode,
            ],
            FilterTypeEnum::DATE => [
                new DateRangeMatchMode,
                new NotInDateRangeMatchMode,
            ],
        };

        if ($this->nullable) {
            $modes[] = new EmptyMatchMode;
            $modes[] = new NotEmptyMatchMode;
        }

        return $modes;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'column' => $this->column,
            'label' => $this->getLabel(),
            'allowedMatchModes' => array_map(fn (MatchModeContract $m) => $m->key(), $this->allowedMatchModes),
            'defaultMatchMode' => $this->getDefaultMatchMode()->key(),
            'nullable' => $this->nullable,
            'options' => $this->options,
            'meta' => $this->meta,
            'relation' => $this->relation,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
