<?php

namespace Ameax\FilterCore\Concerns;

use Ameax\FilterCore\Collection\CollectionApplicator;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Selections\FilterSelection;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     * Get a filter by its key.
     */
    public static function getFilterByKey(string $key): ?Filter
    {
        foreach (static::getFilters() as $filter) {
            if ($filter->resolveKey() === $key) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * Get all filter keys for this model.
     *
     * @return array<string>
     */
    public static function getFilterKeys(): array
    {
        return array_map(
            fn (Filter $filter) => $filter->resolveKey(),
            static::getFilters()
        );
    }

    /**
     * Validate a selection against this model's filters.
     *
     * @return array{valid: bool, unknown: array<string>, known: array<string>}
     */
    public static function validateSelection(FilterSelection $selection): array
    {
        $modelKeys = static::getFilterKeys();
        $selectionKeys = array_map(
            fn (FilterValue $fv) => $fv->getFilterKey(),
            $selection->all()
        );

        $unknown = array_diff($selectionKeys, $modelKeys);
        $known = array_intersect($selectionKeys, $modelKeys);

        return [
            'valid' => empty($unknown),
            'unknown' => array_values($unknown),
            'known' => array_values($known),
        ];
    }

    /**
     * Clear the cached filters (useful for testing).
     */
    public static function clearFilterCache(): void
    {
        unset(static::$resolvedFilters[static::class]);
    }

    /**
     * Scope to apply multiple filter values or a FilterSelection.
     *
     * For FilterSelection with nested groups (AND/OR logic), use applySelection() instead.
     *
     * @param  Builder<static>  $query
     * @param  array<FilterValue>|FilterSelection  $filters
     * @return Builder<static>
     *
     * @example
     * // With array
     * Koi::query()->applyFilters([
     *     FilterValue::for(StatusFilter::class)->is('active'),
     *     FilterValue::for(CountFilter::class)->greaterThan(10),
     * ])->get();
     *
     * // With FilterSelection (simple AND logic)
     * Koi::query()->applyFilters($selection)->get();
     */
    public function scopeApplyFilters(Builder $query, array|FilterSelection $filters): Builder
    {
        // For FilterSelection with nested groups, delegate to applySelection
        if ($filters instanceof FilterSelection && $filters->hasNestedGroups()) {
            return $this->scopeApplySelection($query, $filters);
        }

        $filterValues = $filters instanceof FilterSelection ? $filters->all() : $filters;

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

    /**
     * Scope to apply a FilterSelection with optional strict mode.
     *
     * This method properly handles nested AND/OR groups.
     * When strict is false, unknown filters are silently ignored.
     *
     * @param  Builder<static>  $query
     * @param  bool  $strict  If false, unknown filters are silently ignored
     * @return Builder<static>
     *
     * @example
     * // Strict mode (default) - throws exception on unknown filters
     * Koi::query()->applySelection($selection)->get();
     *
     * // Tolerant mode - ignores unknown filters
     * Koi::query()->applySelection($selection, strict: false)->get();
     *
     * // With OR logic
     * $selection = FilterSelection::make()
     *     ->where(StatusFilter::class)->is('active')
     *     ->orWhere(fn($g) => $g->where(StatusFilter::class)->is('pending'));
     * Koi::query()->applySelection($selection)->get();
     */
    public function scopeApplySelection(
        Builder $query,
        FilterSelection $selection,
        bool $strict = true
    ): Builder {
        if (! $selection->hasFilters()) {
            return $query;
        }

        // In non-strict mode, we need to filter out unknown filters
        // For now, non-strict mode only works with flat selections
        if (! $strict && ! $selection->hasNestedGroups()) {
            $validKeys = static::getFilterKeys();
            $validFilters = array_filter(
                $selection->all(),
                fn (FilterValue $fv) => in_array($fv->getFilterKey(), $validKeys, true)
            );

            if (empty($validFilters)) {
                return $query;
            }

            $applicator = QueryApplicator::for($query)
                ->withFilters(static::getFilters())
                ->applyFilters($validFilters);

            return $applicator->getQuery();
        }

        // Use the full group-based application for complex selections
        $applicator = QueryApplicator::for($query)
            ->withFilters(static::getFilters())
            ->applySelection($selection);

        return $applicator->getQuery();
    }

    // ========================================================================
    // Collection Filtering Methods
    // ========================================================================

    /**
     * Filter a collection using the model's defined filters.
     *
     * @param  Collection<int|string, mixed>  $collection
     * @param  array<FilterValue>|FilterSelection  $filters
     * @return Collection<int|string, mixed>
     *
     * @example
     * $collection = Koi::all();
     * $filtered = Koi::filterCollection($collection, [
     *     FilterValue::for(StatusFilter::class)->is('active'),
     * ]);
     */
    public static function filterCollection(Collection $collection, array|FilterSelection $filters): Collection
    {
        if ($filters instanceof FilterSelection) {
            return static::filterCollectionWithSelection($collection, $filters);
        }

        if (empty($filters)) {
            return $collection;
        }

        $applicator = CollectionApplicator::for($collection)
            ->withFilters(static::getFilters())
            ->applyFilters($filters);

        return $applicator->getCollection();
    }

    /**
     * Filter a collection using a single FilterValue.
     *
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     *
     * @example
     * $filtered = Koi::filterCollectionWith($collection,
     *     FilterValue::for(StatusFilter::class)->is('active')
     * );
     */
    public static function filterCollectionWith(Collection $collection, FilterValue $filterValue): Collection
    {
        return static::filterCollection($collection, [$filterValue]);
    }

    /**
     * Filter a collection using a FilterSelection.
     *
     * This method properly handles nested AND/OR groups.
     *
     * @param  Collection<int|string, mixed>  $collection
     * @return Collection<int|string, mixed>
     *
     * @example
     * $selection = FilterSelection::make()
     *     ->where(StatusFilter::class)->is('active')
     *     ->orWhere(fn($g) => $g->where(StatusFilter::class)->is('pending'));
     *
     * $filtered = Koi::filterCollectionWithSelection($collection, $selection);
     */
    public static function filterCollectionWithSelection(Collection $collection, FilterSelection $selection): Collection
    {
        if (! $selection->hasFilters()) {
            return $collection;
        }

        $applicator = CollectionApplicator::for($collection)
            ->withFilters(static::getFilters())
            ->applySelection($selection);

        return $applicator->getCollection();
    }
}
