# Collection Filtering

Apply the same filter logic to in-memory Laravel Collections instead of database queries.

## Overview

Collection filtering uses the same filters, match modes, and selections as query filtering, but operates on already-loaded data. This is useful for:

- Filtering data without additional database queries
- Unit testing filter logic
- Processing data from external sources (APIs, files)
- Client-side filtering in combination with server-side

**Key guarantee**: The same filters produce identical results whether applied to a query or a collection.

## Basic Collection Filtering

### Via Model Method

```php
use App\Models\User;
use App\Filters\StatusFilter;
use Ameax\FilterCore\Data\FilterValue;

// Load all data
$collection = User::all();

// Filter the collection
$filtered = User::filterCollection($collection, [
    FilterValue::for(StatusFilter::class)->is('active'),
]);

// $filtered contains only users where status = 'active'
```

### Multiple Filters

```php
$filtered = User::filterCollection($collection, [
    FilterValue::for(StatusFilter::class)->any(['active', 'pending']),
    FilterValue::for(CountFilter::class)->gte(10),
]);
```

## Collection Filtering with Selections

For complex filter logic, use `filterCollectionWithSelection()`:

```php
use Ameax\FilterCore\Selections\FilterSelection;

$collection = User::all();

// AND logic
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(50);

$filtered = User::filterCollectionWithSelection($collection, $selection);
```

### OR Logic

```php
use Ameax\FilterCore\Selections\FilterGroup;

// status = 'active' OR status = 'pending'
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

$filtered = User::filterCollectionWithSelection($collection, $selection);
```

### Complex Nested Logic

```php
// (status = 'active' AND count >= 100) OR (status = 'pending')
$selection = FilterSelection::makeOr()
    ->andWhere(function (FilterGroup $g) {
        $g->where(StatusFilter::class)->is('active');
        $g->where(CountFilter::class)->gte(100);
    })
    ->andWhere(function (FilterGroup $g) {
        $g->where(StatusFilter::class)->is('pending');
    });

$filtered = User::filterCollectionWithSelection($collection, $selection);
```

## CollectionApplicator Direct Usage

For more control, use `CollectionApplicator` directly:

```php
use Ameax\FilterCore\Collection\CollectionApplicator;
use Ameax\FilterCore\Data\FilterValue;

$collection = User::all();

$filtered = CollectionApplicator::for($collection)
    ->withFilters([StatusFilter::class, CountFilter::class])
    ->applyFilters([
        FilterValue::for(StatusFilter::class)->is('active'),
        FilterValue::for(CountFilter::class)->between(10, 100),
    ])
    ->getCollection();
```

### With Selection

```php
$filtered = CollectionApplicator::for($collection)
    ->withFilters([StatusFilter::class, CountFilter::class])
    ->applySelection($selection)
    ->getCollection();
```

## Query vs Collection: Same Results

The key guarantee is that query and collection filtering produce identical results:

```php
use Ameax\FilterCore\Data\FilterValue;

$filters = [
    FilterValue::for(StatusFilter::class)->any(['active', 'pending']),
    FilterValue::for(CountFilter::class)->gte(10),
];

// Query filtering (database)
$queryResult = User::query()
    ->applyFilters($filters)
    ->pluck('id')
    ->sort()
    ->values()
    ->all();

// Collection filtering (in-memory)
$collectionResult = User::filterCollection(User::all(), $filters)
    ->pluck('id')
    ->sort()
    ->values()
    ->all();

// Results are identical
assert($queryResult === $collectionResult);
```

## Match Mode Support

All match modes work with collection filtering:

| Mode | Collection Behavior |
|------|---------------------|
| `is` | `$item->column === $value` |
| `isNot` | `$item->column !== $value` |
| `any` | `in_array($item->column, $values)` |
| `none` | `!in_array($item->column, $values)` |
| `gt` | `$item->column > $value` |
| `gte` | `$item->column >= $value` |
| `lt` | `$item->column < $value` |
| `lte` | `$item->column <= $value` |
| `between` | `$value->min <= $item->column <= $value->max` |
| `contains` | `str_contains($item->column, $value)` |
| `startsWith` | `str_starts_with($item->column, $value)` |
| `endsWith` | `str_ends_with($item->column, $value)` |
| `regex` | `preg_match($value, $item->column)` |
| `empty` | `$item->column === null \|\| $item->column === ''` |
| `notEmpty` | `$item->column !== null && $item->column !== ''` |

## Relation Filters in Collections

Relation filters require eager-loaded relationships:

```php
// Eager load the relationship
$collection = User::with('company')->get();

// Now relation filters work on the collection
$filtered = User::filterCollectionWithSelection($collection, $selection);
```

**Note**: If the relationship isn't loaded, the filter may not work correctly or may trigger additional queries.

## Use Cases

### 1. Filter Already-Loaded Data

```php
// Data loaded for other purposes
$users = User::with('orders', 'profile')->get();

// Filter without new queries
$activeUsers = User::filterCollection($users, [
    FilterValue::for(StatusFilter::class)->is('active'),
]);
```

### 2. Unit Testing

```php
public function test_active_filter_returns_only_active_items()
{
    // Create test data
    $collection = collect([
        (object) ['id' => 1, 'status' => 'active'],
        (object) ['id' => 2, 'status' => 'inactive'],
        (object) ['id' => 3, 'status' => 'active'],
    ]);

    $filtered = CollectionApplicator::for($collection)
        ->withFilters([StatusFilter::class])
        ->applyFilter(FilterValue::for(StatusFilter::class)->is('active'))
        ->getCollection();

    $this->assertCount(2, $filtered);
    $this->assertEquals([1, 3], $filtered->pluck('id')->all());
}
```

### 3. External Data Sources

```php
// Data from API
$apiResponse = Http::get('https://api.example.com/products')->json();
$collection = collect($apiResponse['data'])->map(fn($item) => (object) $item);

// Apply same filter logic
$filtered = CollectionApplicator::for($collection)
    ->withFilters([ProductStatusFilter::class])
    ->applyFilter(FilterValue::for(ProductStatusFilter::class)->is('available'))
    ->getCollection();
```

### 4. Cached Data

```php
// Get data from cache
$users = Cache::remember('all_users', 3600, fn() => User::all());

// Filter cached data
$filtered = User::filterCollection($users, [
    FilterValue::for(StatusFilter::class)->is('active'),
]);
```

## Performance Considerations

1. **Memory**: Collection filtering loads all data into memory first
2. **Large datasets**: For large datasets, prefer query filtering
3. **Eager loading**: Pre-load relationships needed for relation filters
4. **Re-filtering**: Collection filtering is efficient for re-filtering the same data with different criteria

```php
// Good: Filter same data multiple ways
$allUsers = User::all();
$activeUsers = User::filterCollection($allUsers, [/* active filters */]);
$pendingUsers = User::filterCollection($allUsers, [/* pending filters */]);

// Better for large data: Use query filtering
$activeUsers = User::query()->applyFilters([/* active filters */])->get();
$pendingUsers = User::query()->applyFilters([/* pending filters */])->get();
```

## Limitations

1. **Relation filters need eager loading** - Relationships must be loaded beforehand
2. **Database functions not available** - No `LOWER()`, `DATE()`, etc.
3. **Regex syntax** - Uses PHP's `preg_match()`, not database regex

## Next Steps

- [Dynamic Filters](./07-dynamic-filters.md) - Create filters at runtime
- [Filter Selections](./04-filter-selections.md) - Complex AND/OR logic
