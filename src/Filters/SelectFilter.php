<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Filters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\Dynamic\DynamicSelectFilter;
use Ameax\FilterCore\MatchModes\AnyMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;
use Ameax\FilterCore\MatchModes\NoneMatchMode;
use Illuminate\Validation\Rule;

/**
 * Base class for SELECT type filters.
 */
abstract class SelectFilter extends Filter implements HasOptions
{
    /**
     * Create a dynamic select filter with the given key.
     */
    public static function dynamic(string $key): DynamicSelectFilter
    {
        return DynamicSelectFilter::create($key);
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::SELECT;
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
            new AnyMatchMode,
            new NoneMatchMode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(MatchModeContract $mode): array
    {
        $options = $this->options();

        // If no options defined, allow any value
        if (empty($options)) {
            return [
                'value' => 'required',
            ];
        }

        $allowedValues = array_keys($options);

        // ANY and NONE modes expect arrays
        if (in_array($mode->key(), ['any', 'none'], true)) {
            return [
                'value' => 'required|array',
                'value.*' => Rule::in($allowedValues),
            ];
        }

        return [
            'value' => ['required', Rule::in($allowedValues)],
        ];
    }

    /**
     * Type-safe value method for select filters.
     *
     * Can be used directly for strict typing, bypassing sanitize/validate.
     * Accepts string for IS/IS_NOT modes or array for ANY/NONE modes.
     *
     * @param  string|array<string>  $value
     * @return string|array<string>
     */
    public function typedValue(string|array $value): string|array
    {
        return $value;
    }
}
