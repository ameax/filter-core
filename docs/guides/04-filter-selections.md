# Filter Selections & Groups

Filter selections allow you to combine multiple filters with AND/OR logic, name them, and persist them as JSON.

## Basic Selection

A `FilterSelection` groups multiple filter values together:

```php
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Data\FilterValue;
use App\Filters\StatusFilter;
use App\Filters\CountFilter;

// Create a selection with multiple filters
$selection = FilterSelection::make('Active Premium Users')
    ->description('Users with active status and high engagement')
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(100);

// Apply to query
$users = User::query()->applySelection($selection)->get();
```

By default, filters in a selection use **AND logic**:

```sql
WHERE status = 'active' AND count > 100
```

## Creating Selections

### Named Selection

```php
$selection = FilterSelection::make('My Filter')
    ->description('Optional description')
    ->where(StatusFilter::class)->is('active');
```

### Anonymous Selection

```php
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active');
```

### Using add() vs where()

Both produce the same result - `where()` is more fluent:

```php
// Using add()
$selection = FilterSelection::make()
    ->add(FilterValue::for(StatusFilter::class)->is('active'))
    ->add(FilterValue::for(CountFilter::class)->gt(10));

// Using where() - more readable
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);
```

## OR Logic

### Simple OR Selection

Use `makeOr()` to create a selection where filters use OR logic:

```php
// Find users with status = 'active' OR status = 'pending'
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

$users = User::query()->applySelection($selection)->get();
// SQL: WHERE status = 'active' OR status = 'pending'
```

### Nested OR Group

Add an OR group within an AND selection using `orWhere()`:

```php
use Ameax\FilterCore\Selections\FilterGroup;

// count > 10 AND (status = 'active' OR status = 'pending')
$selection = FilterSelection::make()
    ->where(CountFilter::class)->gt(10)
    ->orWhere(function (FilterGroup $group) {
        $group->where(StatusFilter::class)->is('active');
        $group->where(StatusFilter::class)->is('pending');
    });
```

### Nested AND Group

Add an AND group within an OR selection using `andWhere()`:

```php
// (status = 'active' AND count > 100) OR (status = 'pending')
$selection = FilterSelection::makeOr()
    ->andWhere(function (FilterGroup $group) {
        $group->where(StatusFilter::class)->is('active');
        $group->where(CountFilter::class)->gt(100);
    })
    ->andWhere(function (FilterGroup $group) {
        $group->where(StatusFilter::class)->is('pending');
    });
```

## Complex Nested Logic

Groups can be nested multiple levels deep:

```php
// count >= 10 AND ((status = 'active' AND count > 50) OR (status = 'inactive'))
$selection = FilterSelection::make()
    ->where(CountFilter::class)->gte(10)
    ->orWhere(function (FilterGroup $or) {
        $or->andWhere(function (FilterGroup $and) {
            $and->where(StatusFilter::class)->is('active');
            $and->where(CountFilter::class)->gt(50);
        });
        $or->andWhere(function (FilterGroup $and) {
            $and->where(StatusFilter::class)->is('inactive');
        });
    });
```

## FilterGroup Direct Usage

You can also use `FilterGroup` directly:

```php
use Ameax\FilterCore\Selections\FilterGroup;

// Create groups
$andGroup = FilterGroup::and()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

$orGroup = FilterGroup::or()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

// Nest groups
$andGroup->addGroup($orGroup);
```

## Selection Inspection

Check the contents of a selection:

```php
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->between(10, 100);

// Check for specific filters
$selection->has(StatusFilter::class);     // true
$selection->has(CategoryFilter::class);   // false

// Get specific filter value
$statusValue = $selection->get(StatusFilter::class);
$statusValue->getValue();      // 'active'
$statusValue->getMatchMode();  // IsMatchMode

// Count and state
$selection->count();           // 2
$selection->hasFilters();      // true
$selection->hasNestedGroups(); // false (no OR groups)

// Get all filter values (flattened)
$allValues = $selection->all(); // array of FilterValue
```

## Selection Modification

```php
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

// Remove a filter
$selection->remove(CountFilter::class);
$selection->count(); // 1

// Clear all filters
$selection->clear();
$selection->count(); // 0
```

## JSON Serialization

### Save to JSON

```php
$selection = FilterSelection::make('My Filter')
    ->description('Filter description')
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

$json = $selection->toJson();
```

**Simple format** (AND-only, no nested groups):

```json
{
  "name": "My Filter",
  "description": "Filter description",
  "filters": [
    {"filter": "StatusFilter", "mode": "is", "value": "active"},
    {"filter": "CountFilter", "mode": "gt", "value": 10}
  ]
}
```

**Complex format** (with nested groups):

```json
{
  "name": "Complex Filter",
  "description": null,
  "group": {
    "operator": "and",
    "items": [
      {"filter": "CountFilter", "mode": "gt", "value": 10},
      {
        "operator": "or",
        "items": [
          {"filter": "StatusFilter", "mode": "is", "value": "active"},
          {"filter": "StatusFilter", "mode": "is", "value": "pending"}
        ]
      }
    ]
  }
}
```

### Load from JSON

```php
// From JSON string
$selection = FilterSelection::fromJson($json);

// From array
$selection = FilterSelection::fromArray([
    'name' => 'Loaded Filter',
    'filters' => [
        ['filter' => 'StatusFilter', 'mode' => 'is', 'value' => 'active'],
    ],
]);
```

### Round-Trip

```php
$original = FilterSelection::make('Test')
    ->where(StatusFilter::class)->any(['active', 'pending'])
    ->where(CountFilter::class)->between(10, 100);

// Save
$json = $original->toJson();

// Restore
$restored = FilterSelection::fromJson($json);

// Both produce identical query results
```

## Applying Selections

### Via Model Scope

```php
// Using Filterable trait
$users = User::query()->applySelection($selection)->get();
```

### Via QueryApplicator

```php
use Ameax\FilterCore\Query\QueryApplicator;

$users = QueryApplicator::for(User::query())
    ->withFilters([StatusFilter::class, CountFilter::class])
    ->applySelection($selection)
    ->getQuery()
    ->get();
```

### With Validation

```php
// Strict mode (default) - throws on unknown filters
$users = User::query()->applySelection($selection)->get();

// Tolerant mode - ignores unknown filters
$users = User::query()->applySelection($selection, strict: false)->get();

// Manual validation
$result = User::validateSelection($selection);
// ['valid' => true, 'unknown' => [], 'known' => ['StatusFilter', 'CountFilter']]
```

## Real-World Examples

### Filter Presets

```php
// Define presets
$presets = [
    'active_users' => FilterSelection::make('Active Users')
        ->where(StatusFilter::class)->is('active'),

    'high_value' => FilterSelection::make('High Value')
        ->where(StatusFilter::class)->is('active')
        ->where(CountFilter::class)->gt(100),

    'needs_attention' => FilterSelection::makeOr()
        ->where(StatusFilter::class)->is('pending')
        ->where(StatusFilter::class)->is('review'),
];

// Store as JSON in database
$preset->json_config = $presets['high_value']->toJson();

// Load and apply
$selection = FilterSelection::fromJson($preset->json_config);
$users = User::query()->applySelection($selection)->get();
```

### API Filter Endpoint

```php
// POST /api/users/filter
public function filter(Request $request)
{
    $selection = FilterSelection::fromArray([
        'filters' => $request->input('filters', []),
    ]);

    return User::query()
        ->applySelection($selection)
        ->paginate();
}
```

### Dynamic Form Builder

```php
// Build selection from form data
$selection = FilterSelection::make();

if ($request->filled('status')) {
    $selection->where(StatusFilter::class)->any($request->input('status'));
}

if ($request->filled('min_count')) {
    $selection->where(CountFilter::class)->gte($request->input('min_count'));
}

if ($request->filled('search')) {
    $selection->where(NameFilter::class)->contains($request->input('search'));
}
```

## Next Steps

- [Relation Filters](./05-relation-filters.md) - Filter through relationships
- [Collection Filtering](./06-collection-filtering.md) - Apply selections to collections
