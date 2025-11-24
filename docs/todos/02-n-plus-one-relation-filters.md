# TODO: N+1 Queries with Multiple Relation Filters

**Priority:** ~~Medium~~ → **RESOLVED**
**Status:** ✅ Resolved

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

## ✅ Solution Implemented

The `QueryApplicator::applyFilters()` method now automatically groups filters by relation and mode, combining them into a single `whereHas()` or `whereDoesntHave()` call.

### Implementation Details

**src/Query/QueryApplicator.php**

1. **groupFiltersByRelation()** - Groups filter values by relation and RelationMode
   - Separates direct filters (no relation) from relation filters
   - Groups relation filters by `relation:mode` key (e.g., `"pond:has"`)
   - Different RelationModes are kept separate (HAS, DOESNT_HAVE, HAS_NONE)

2. **applyGroupedRelationFilters()** - Applies multiple filters in one whereHas()
   - Takes all filters for a specific relation+mode combination
   - Wraps them in a single `whereHas()` or `whereDoesntHave()` callback
   - All filters execute within the same relation subquery

3. **applyFilterToRelationQuery()** - Applies individual filter within relation callback
   - Handles sanitization, validation, and type checking
   - Applies custom filter logic or match mode
   - Used within the grouped relation callback

### Before (Unoptimized)

```php
Koi::query()->applyFilters([
    FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),
    FilterValue::for(PondCapacityFilter::class)->gt(1000),
]);

// Generated SQL:
// WHERE EXISTS (SELECT * FROM ponds WHERE kois.pond_id = ponds.id AND water_type = 'fresh')
// AND EXISTS (SELECT * FROM ponds WHERE kois.pond_id = ponds.id AND capacity > 1000)
// → Two separate EXISTS subqueries
```

### After (Optimized)

```php
// Same code, now optimized automatically
Koi::query()->applyFilters([
    FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),
    FilterValue::for(PondCapacityFilter::class)->gt(1000),
]);

// Generated SQL:
// WHERE EXISTS (
//   SELECT * FROM ponds
//   WHERE kois.pond_id = ponds.id
//   AND water_type = 'fresh'
//   AND capacity > 1000
// )
// → Single combined EXISTS subquery
```

### Compatibility

- ✅ **100% backward compatible** - No API changes
- ✅ **Automatic** - Works transparently via `applyFilters()`
- ✅ **Respects RelationMode** - HAS and DOESNT_HAVE stay separate
- ✅ **Preserves behavior** - Results identical to individual application

### Tests

**tests/Query/RelationFilterOptimizationTest.php** - 8 comprehensive tests:
- Combining 2-3 filters on same relation
- Separating filters with different RelationModes
- Mixing direct and relation filters
- Result correctness verification
- Single filter (no change in behavior)

All 293 tests passing ✅

## Original Proposed Solution

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
