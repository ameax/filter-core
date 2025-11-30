# TODO: Missing Debugging Tools

**Priority:** Low
**Status:** Completed

## Solution

Implemented debugging tools in `FilterSelection`:

```php
// Get SQL with placeholders
$selection->toSql();
// → "select * from `kois` where `status` = ? and `count` > ?"

// Get SQL with bindings interpolated
$selection->toSqlWithBindings();
// → "select * from `kois` where `status` = 'active' and `count` > 10"

// Human-readable explanation
$selection->explain();
// → "KoiStatusFilter IS 'active' AND KoiCountFilter GT 10"

// Full debug info
$selection->debug();
// → ['sql' => ..., 'sql_with_bindings' => ..., 'bindings' => [...], 'filters' => [...], 'explanation' => ...]

// Dump and die
$selection->dd();
```

All methods accept an optional query parameter, or use the model class if set via `forModel()`.

---

## Original Problem

Hard to debug complex filter queries. No built-in tools to:
- See the generated SQL for a FilterSelection
- Get a human-readable explanation of filter logic
- Dump query details with bindings
- Trace which filters were applied

## Proposed Solutions

### 1. SQL Preview

```php
// Show SQL for a selection without executing
$selection->toSql(Koi::query());
// → "SELECT * FROM kois WHERE status = ? AND count > ?"

// With bindings
$selection->toSqlWithBindings(Koi::query());
// → "SELECT * FROM kois WHERE status = 'active' AND count > 10"
```

### 2. Human-Readable Explanation

```php
$selection->explain();
// → "Active kois with count greater than 10"

// Detailed explanation
$selection->explain(detailed: true);
// → "StatusFilter: status IS 'active' AND CountFilter: count GREATER_THAN 10"
```

### 3. Dump and Die

```php
QueryApplicator::for($query)
    ->withFilters([...])
    ->applyFilters([...])
    ->dd(); // Shows SQL, bindings, and query plan

// Or via trait
Koi::query()
    ->applySelection($selection)
    ->dumpFilters(); // Shows which filters were applied
```

### 4. Query Plan / EXPLAIN

```php
$selection->getQueryPlan(Koi::query());
// → Executes EXPLAIN and returns analysis
```

### 5. Filter Trace

```php
// Log all filter applications
QueryApplicator::for($query)
    ->withFilters($filters)
    ->enableTrace()
    ->applyFilters($filterValues)
    ->getTrace();
// → Array of applied filters with timing info
```

## Laravel Debugbar Integration

Add a collector for Laravel Debugbar:

```php
// FilterCoreServiceProvider
if (config('filter-core.debugbar_enabled') && class_exists('Debugbar')) {
    Debugbar::addCollector(new FilterCollector());
}
```

The collector would show:
- Number of filters applied
- Filter keys and values
- Generated SQL queries
- Execution time per filter

## Laravel Telescope Integration

```php
// FilterCoreServiceProvider
if (config('filter-core.telescope_enabled') && class_exists('Telescope')) {
    FilterSelection::observe(FilterTelescope::class);
}
```

## Implementation Priority

1. **High:** `toSql()` method - Most useful for debugging
2. **Medium:** `explain()` method - Good for UX
3. **Low:** Debugbar/Telescope integration - Nice to have

## Related Files

- `src/Selections/FilterSelection.php` - Add debug methods
- `src/Query/QueryApplicator.php` - Add trace capability
- Create `src/Debug/FilterCollector.php` for Debugbar
- Create `src/Debug/FilterTelescope.php` for Telescope
