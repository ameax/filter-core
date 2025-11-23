<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicIntegerFilter;
use Ameax\FilterCore\MatchModes\BetweenMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;
use Ameax\FilterCore\MatchModes\LessThanMatchMode;

/**
 * Base class for INTEGER type filters.
 */
abstract class IntegerFilter extends Filter
{
    /**
     * Create a dynamic integer filter with the given key.
     */
    public static function dynamic(string $key): DynamicIntegerFilter
    {
        return DynamicIntegerFilter::create($key);
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::INTEGER;
    }

    public function defaultMode(): MatchModeContract
    {
        return new IsMatchMode;
    }

    public function allowedModes(): array
    {
        return [
            new IsMatchMode,
            new IsNotMatchMode,
            new GreaterThanMatchMode,
            new LessThanMatchMode,
            new BetweenMatchMode,
        ];
    }

    /**
     * Sanitize numeric strings to integers or BetweenValue.
     */
    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        // Already a BetweenValue, return as-is
        if ($value instanceof BetweenValue) {
            return $value;
        }

        // Convert array to BetweenValue for BETWEEN mode
        if ($mode->key() === 'between' && is_array($value)) {
            $min = $value['min'] ?? $value[0] ?? null;
            $max = $value['max'] ?? $value[1] ?? null;

            if ($min !== null && $max !== null && is_numeric($min) && is_numeric($max)) {
                return new BetweenValue((int) $min, (int) $max);
            }

            // Return array if conversion fails, validation will catch it
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        // BetweenValue is already type-safe, no additional validation needed
        // The typedValue() method ensures type safety for BETWEEN mode
        if ($mode->key() === 'between') {
            return [];
        }

        return [
            'value' => 'required|numeric',
        ];
    }

    /**
     * Type-safe value method for integer filters.
     *
     * Can be used directly for strict typing, bypassing sanitize/validate.
     * Accepts int for standard modes or BetweenValue for BETWEEN mode.
     */
    public function typedValue(int|BetweenValue $value): int|BetweenValue
    {
        return $value;
    }
}
