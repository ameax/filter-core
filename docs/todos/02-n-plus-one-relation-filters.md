# TODO: N+1 Queries with Multiple Relation Filters

**Priority:** Medium
**Status:** Open

## Problem

Each relation filter generates a separate `whereHas()` subquery, even when filtering the same relation multiple times.

```php
Koi::query()->applyFilters([
    FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),
    FilterValue::for(PondCapacityFilter::class)->gt(1000),
]);

// Generates:
// WHERE EXISTS (SELECT * FROM ponds WHERE ... AND water_type = 'fresh')
// AND EXISTS (SELECT * FROM ponds WHERE ... AND capacity > 1000)

// Could be optimized to:
// WHERE EXISTS (SELECT * FROM ponds WHERE ... AND water_type = 'fresh' AND capacity > 1000)
```

## Impact

Performance degradation for models with multiple relation filters.

## Solution

Group filters by relation and apply them in a single `whereHas()`:

```php
class QueryApplicator {
    protected function applyRelationFilters(array $filtersByRelation): void
    {
        foreach ($filtersByRelation as $relation => $filters) {
            $this->query->whereHas($relation, function($query) use ($filters) {
                foreach ($filters as $filter) {
                    $this->applyFilter($query, $filter);
                }
            });
        }
    }
}
```

## Implementation Steps

1. Group applied filters by relation in `QueryApplicator`
2. Detect when multiple filters target the same relation
3. Combine them into a single `whereHas()` call
4. Add tests to verify query optimization
5. Measure performance improvement with benchmarks

## Related Files

- `src/Query/QueryApplicator.php` - Main query application logic
- `src/Filters/Filter.php` - Relation information via `getRelation()`
