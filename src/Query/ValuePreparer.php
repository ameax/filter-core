<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Query;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Filters\Filter;
use Illuminate\Support\Facades\Validator;
use TypeError;

/**
 * Prepares a filter value before it is applied: sanitize, type check, validate.
 *
 * One place for what QueryApplicator, CollectionApplicator and the Resolver all
 * need, so that the three cannot drift apart.
 *
 * @internal
 */
final class ValuePreparer
{
    /**
     * @throws FilterValidationException When the type check or validation fails
     */
    public static function prepare(Filter $filter, string $filterKey, MatchModeContract $mode, mixed $value): mixed
    {
        $value = $filter->sanitizeValue($value, $mode);
        $value = self::typed($filter, $filterKey, $value);
        self::validate($filter, $filterKey, $mode, $value);

        return $value;
    }

    /**
     * Apply the filter's typedValue() method if it exists; a TypeError there means
     * the value has the wrong type for this filter.
     *
     * @throws FilterValidationException
     */
    public static function typed(Filter $filter, string $filterKey, mixed $value): mixed
    {
        if (! method_exists($filter, 'typedValue')) {
            return $value;
        }

        try {
            return $filter->typedValue($value);
        } catch (TypeError $e) {
            throw new FilterValidationException(
                $filterKey,
                ['value' => ['The value type is invalid for this filter: '.$e->getMessage()]]
            );
        }
    }

    /**
     * @throws FilterValidationException
     */
    public static function validate(Filter $filter, string $filterKey, MatchModeContract $mode, mixed $value): void
    {
        $rules = $filter->validationRules($mode);

        if (empty($rules)) {
            return;
        }

        $validator = Validator::make(['value' => $value], $rules);

        if ($validator->fails()) {
            throw new FilterValidationException($filterKey, $validator->errors()->toArray());
        }
    }
}
