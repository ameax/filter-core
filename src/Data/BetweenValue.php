<?php

namespace Ameax\FilterCore\Data;

/**
 * Value object for BETWEEN match mode values.
 *
 * Provides type-safe representation of min/max range values.
 */
readonly class BetweenValue
{
    public function __construct(
        public int|float $min,
        public int|float $max,
    ) {}

    /**
     * Create from an array with min/max keys or indexed values.
     *
     * @param  array{min?: int|float, max?: int|float, 0?: int|float, 1?: int|float}  $array
     */
    public static function fromArray(array $array): self
    {
        $min = $array['min'] ?? $array[0] ?? null;
        $max = $array['max'] ?? $array[1] ?? null;

        if ($min === null || $max === null) {
            throw new \InvalidArgumentException('BetweenValue requires both min and max values');
        }

        return new self($min, $max);
    }

    /**
     * Convert to array format.
     *
     * @return array{min: int|float, max: int|float}
     */
    public function toArray(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
        ];
    }
}
