<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicTextFilter;
use Ameax\FilterCore\MatchModes\ContainsMatchMode;
use Ameax\FilterCore\MatchModes\EndsWithMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;
use Ameax\FilterCore\MatchModes\RegexMatchMode;
use Ameax\FilterCore\MatchModes\StartsWithMatchMode;

/**
 * Base class for TEXT type filters.
 */
abstract class TextFilter extends Filter
{
    /**
     * Create a dynamic text filter with the given key.
     */
    public static function dynamic(string $key): DynamicTextFilter
    {
        return DynamicTextFilter::create($key);
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::TEXT;
    }

    public function defaultMode(): MatchModeContract
    {
        return new ContainsMatchMode;
    }

    public function allowedModes(): array
    {
        return [
            new ContainsMatchMode,
            new StartsWithMatchMode,
            new EndsWithMatchMode,
            new RegexMatchMode,
            new IsMatchMode,
            new IsNotMatchMode,
        ];
    }

    /**
     * Sanitize text values by trimming whitespace.
     */
    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        return [
            'value' => 'required|string',
        ];
    }

    /**
     * Type-safe value method for text filters.
     *
     * Can be used directly for strict typing, bypassing sanitize/validate.
     */
    public function typedValue(string $value): string
    {
        return $value;
    }
}
