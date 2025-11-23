<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicBooleanFilter;
use Ameax\FilterCore\MatchModes\IsMatchMode;

/**
 * Base class for BOOLEAN type filters.
 */
abstract class BooleanFilter extends Filter
{
    /**
     * Create a dynamic boolean filter with the given key.
     */
    public static function dynamic(string $key): DynamicBooleanFilter
    {
        return DynamicBooleanFilter::create($key);
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::BOOLEAN;
    }

    public function defaultMode(): MatchModeContract
    {
        return new IsMatchMode();
    }

    public function allowedModes(): array
    {
        return [
            new IsMatchMode(),
        ];
    }

    /**
     * Sanitize boolean-like values to actual booleans.
     */
    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        return [
            'value' => 'required|boolean',
        ];
    }

    /**
     * Type-safe value method for boolean filters.
     *
     * Can be used directly for strict typing, bypassing sanitize/validate.
     */
    public function typedValue(bool $value): bool
    {
        return $value;
    }
}
