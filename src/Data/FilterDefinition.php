<?php

namespace Ameax\FilterCore\Data;

use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;
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
     * @param  array<MatchModeEnum>  $allowedMatchModes
     * @param  array<int|string, mixed>  $options  For SELECT type: ['value' => 'Label', ...]
     * @param  array<string, mixed>  $meta  Additional metadata
     */
    public function __construct(
        protected string $key,
        protected FilterTypeEnum $type,
        protected string $column,
        protected ?string $label = null,
        protected array $allowedMatchModes = [],
        protected ?MatchModeEnum $defaultMatchMode = null,
        protected bool $nullable = false,
        protected array $options = [],
        protected array $meta = [],
    ) {
        if (empty($this->allowedMatchModes)) {
            $this->allowedMatchModes = $this->getDefaultMatchModesForType();
        }

        if ($this->defaultMatchMode === null) {
            $this->defaultMatchMode = $this->allowedMatchModes[0] ?? MatchModeEnum::IS;
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
     * @return array<MatchModeEnum>
     */
    public function getAllowedMatchModes(): array
    {
        return $this->allowedMatchModes;
    }

    public function getDefaultMatchMode(): MatchModeEnum
    {
        return $this->defaultMatchMode ?? MatchModeEnum::IS;
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

    public function isMatchModeAllowed(MatchModeEnum $mode): bool
    {
        return in_array($mode, $this->allowedMatchModes, true);
    }

    /**
     * @return array<MatchModeEnum>
     */
    protected function getDefaultMatchModesForType(): array
    {
        $modes = match ($this->type) {
            FilterTypeEnum::SELECT => [
                MatchModeEnum::IS,
                MatchModeEnum::IS_NOT,
                MatchModeEnum::ANY,
                MatchModeEnum::NONE,
            ],
            FilterTypeEnum::INTEGER => [
                MatchModeEnum::IS,
                MatchModeEnum::IS_NOT,
                MatchModeEnum::GREATER_THAN,
                MatchModeEnum::LESS_THAN,
                MatchModeEnum::BETWEEN,
            ],
            FilterTypeEnum::TEXT => [
                MatchModeEnum::CONTAINS,
                MatchModeEnum::IS,
                MatchModeEnum::IS_NOT,
            ],
            FilterTypeEnum::BOOLEAN => [
                MatchModeEnum::IS,
            ],
        };

        if ($this->nullable) {
            $modes[] = MatchModeEnum::EMPTY;
            $modes[] = MatchModeEnum::NOT_EMPTY;
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
            'allowedMatchModes' => array_map(fn (MatchModeEnum $m) => $m->value, $this->allowedMatchModes),
            'defaultMatchMode' => $this->getDefaultMatchMode()->value,
            'nullable' => $this->nullable,
            'options' => $this->options,
            'meta' => $this->meta,
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
