<?php

namespace Ameax\FilterCore\Concerns;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\Query\QueryApplicator;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for models that support filtering.
 *
 * @mixin Model
 */
trait Filterable
{
    /** @var array<class-string, array<Filter>> Cached filters per model class */
    protected static array $resolvedFilters = [];

    /**
     * Override this method to define the filter resolver callback.
     * The callback is only executed when filters are actually needed (lazy loading).
     *
     * @return Closure(): array<class-string<Filter>|Filter>
     *
     * @example
     * protected static function filterResolver(): Closure
     * {
     *     return fn() => [
     *         StatusFilter::class,
     *         CountFilter::class,
     *         PondWaterTypeFilter::via('pond'),
     *     ];
     * }
     */
    protected static function filterResolver(): Closure
    {
        return fn (): array => [];
    }

    /**
     * Get the resolved filters for this model (cached).
     *
     * @return array<Filter>
     */
    public static function getFilters(): array
    {
        if (! isset(static::$resolvedFilters[static::class])) {
            $resolver = static::filterResolver();
            $filters = $resolver();

            // Normalize: convert class-strings to instances
            static::$resolvedFilters[static::class] = array_map(
                fn ($filter) => is_string($filter) ? $filter::make() : $filter,
                $filters
            );
        }

        return static::$resolvedFilters[static::class];
    }

    /**
     * Clear the cached filters (useful for testing).
     */
    public static function clearFilterCache(): void
    {
        unset(static::$resolvedFilters[static::class]);
    }

    /**
     * Scope to apply multiple filter values.
     *
     * @param  Builder<static>  $query
     * @param  array<FilterValue>  $filterValues
     * @return Builder<static>
     *
     * @example
     * Koi::query()->applyFilters([
     *     FilterValue::for(StatusFilter::class)->is('active'),
     *     FilterValue::for(CountFilter::class)->greaterThan(10),
     * ])->get();
     */
    public function scopeApplyFilters(Builder $query, array $filterValues): Builder
    {
        if (empty($filterValues)) {
            return $query;
        }

        $applicator = QueryApplicator::for($query)
            ->withFilters(static::getFilters())
            ->applyFilters($filterValues);

        return $applicator->getQuery();
    }

    /**
     * Scope to apply a single filter value.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     *
     * @example
     * Koi::query()->applyFilter(
     *     FilterValue::for(StatusFilter::class)->is('active')
     * )->get();
     */
    public function scopeApplyFilter(Builder $query, FilterValue $filterValue): Builder
    {
        return $this->scopeApplyFilters($query, [$filterValue]);
    }
}
