<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicDecimalFilter;
use Ameax\FilterCore\MatchModes\AnyMatchMode;
use Ameax\FilterCore\MatchModes\BetweenMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanOrEqualMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;
use Ameax\FilterCore\MatchModes\LessThanMatchMode;
use Ameax\FilterCore\MatchModes\LessThanOrEqualMatchMode;
use Ameax\FilterCore\MatchModes\NoneMatchMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Base class for DECIMAL type filters.
 *
 * Supports filtering decimal/float columns with configurable precision.
 * Optionally handles columns that store decimals as integers (e.g., cents).
 */
abstract class DecimalFilter extends Filter
{
    /**
     * Create a dynamic decimal filter with the given key.
     */
    public static function dynamic(string $key): DynamicDecimalFilter
    {
        return DynamicDecimalFilter::create($key);
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DECIMAL;
    }

    /**
     * Number of decimal places (default: 2).
     * Override in subclass for different precision.
     */
    public function precision(): int
    {
        return 2;
    }

    /**
     * Whether the value is stored as integer in the database.
     * E.g., 19.99 stored as 1999 (cents).
     * When true, values are multiplied by 10^precision for queries.
     */
    public function storedAsInteger(): bool
    {
        return false;
    }

    /**
     * Minimum allowed value (override in subclass).
     */
    public function min(): ?float
    {
        return null;
    }

    /**
     * Maximum allowed value (override in subclass).
     */
    public function max(): ?float
    {
        return null;
    }

    public function defaultMode(): MatchModeContract
    {
        return new IsMatchMode;
    }

    /**
     * @return array<MatchModeContract>
     */
    public function allowedModes(): array
    {
        return [
            new IsMatchMode,
            new IsNotMatchMode,
            new AnyMatchMode,
            new NoneMatchMode,
            new GreaterThanMatchMode,
            new GreaterThanOrEqualMatchMode,
            new LessThanMatchMode,
            new LessThanOrEqualMatchMode,
            new BetweenMatchMode,
        ];
    }

    /**
     * Convert display value to storage value.
     */
    protected function toStorageValue(float $value): int|float
    {
        if ($this->storedAsInteger()) {
            return (int) round($value * (10 ** $this->precision()));
        }

        return $value;
    }

    /**
     * Sanitize decimal values by converting to float and rounding to precision.
     */
    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle BetweenValue object
        if ($value instanceof \Ameax\FilterCore\Data\BetweenValue) {
            return new \Ameax\FilterCore\Data\BetweenValue(
                $this->sanitizeSingleValue($value->min),
                $this->sanitizeSingleValue($value->max)
            );
        }

        // Handle arrays (for any/none modes or between as array)
        if (is_array($value)) {
            // Check if it's a between-style array with min/max keys
            if (isset($value['min']) && isset($value['max'])) {
                return [
                    'min' => $this->sanitizeSingleValue($value['min']),
                    'max' => $this->sanitizeSingleValue($value['max']),
                ];
            }

            // Check if it's a between-style array with numeric keys [0] and [1]
            if (array_key_exists(0, $value) && array_key_exists(1, $value) && count($value) === 2 && $mode instanceof BetweenMatchMode) {
                return [
                    $this->sanitizeSingleValue($value[0]),
                    $this->sanitizeSingleValue($value[1]),
                ];
            }

            // Otherwise it's an array of values (any/none modes)
            return array_map(fn ($v) => $this->sanitizeSingleValue($v), $value);
        }

        return $this->sanitizeSingleValue($value);
    }

    /**
     * Sanitize a single value to float with proper precision.
     */
    protected function sanitizeSingleValue(mixed $value): float
    {
        if (is_string($value)) {
            $value = (float) $value;
        }

        if (is_int($value)) {
            $value = (float) $value;
        }

        if (is_float($value)) {
            return round($value, $this->precision());
        }

        return (float) $value;
    }

    /**
     * Apply custom filter logic to convert display values to storage values.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        if (! $this->storedAsInteger()) {
            return false; // Use default behavior
        }

        // Convert value(s) to storage format
        $storageValue = $this->convertToStorageFormat($value, $mode);

        // Apply the match mode with the converted value
        $mode->apply($query, $this->column(), $storageValue);

        return true;
    }

    /**
     * Convert value(s) to storage format for storedAsInteger columns.
     */
    protected function convertToStorageFormat(mixed $value, MatchModeContract $mode): mixed
    {
        // Handle BetweenValue
        if ($value instanceof \Ameax\FilterCore\Data\BetweenValue) {
            return [
                'min' => $this->toStorageValue($value->min),
                'max' => $this->toStorageValue($value->max),
            ];
        }

        // Handle arrays
        if (is_array($value)) {
            // Between-style array with min/max keys
            if (isset($value['min']) && isset($value['max'])) {
                return [
                    'min' => $this->toStorageValue((float) $value['min']),
                    'max' => $this->toStorageValue((float) $value['max']),
                ];
            }

            // Between-style array with numeric keys
            if (array_key_exists(0, $value) && array_key_exists(1, $value) && count($value) === 2 && $mode instanceof BetweenMatchMode) {
                return [
                    $this->toStorageValue((float) $value[0]),
                    $this->toStorageValue((float) $value[1]),
                ];
            }

            // Array of values (any/none modes)
            return array_map(fn ($v) => $this->toStorageValue((float) $v), $value);
        }

        // Single value
        return $this->toStorageValue((float) $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        // Empty/notEmpty modes don't require a value
        if (in_array($mode->key(), ['empty', 'notEmpty', 'not_empty'], true)) {
            return [];
        }

        $rules = ['numeric'];

        if ($this->min() !== null) {
            $rules[] = 'min:'.$this->min();
        }

        if ($this->max() !== null) {
            $rules[] = 'max:'.$this->max();
        }

        // Between mode
        if ($mode instanceof BetweenMatchMode) {
            return [
                'value' => ['required', 'array'],
                'value.min' => array_merge(['required'], $rules),
                'value.max' => array_merge(['required'], $rules),
            ];
        }

        // Any/None modes expect arrays
        if (in_array($mode->key(), ['any', 'none'], true)) {
            return [
                'value' => ['required', 'array', 'min:1'],
                'value.*' => array_merge(['required'], $rules),
            ];
        }

        return [
            'value' => array_merge(['required'], $rules),
        ];
    }

    /**
     * Type-safe value method for decimal filters.
     *
     * @param  float|int|string|array<float|int|string>  $value
     * @return float|array<float>
     */
    public function typedValue(float|int|string|array $value): float|array
    {
        if (is_array($value)) {
            return array_map(fn ($v) => (float) $v, $value);
        }

        return (float) $value;
    }
}
