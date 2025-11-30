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

### Model-based Serialization

FilterSelection can optionally include the model class in serialization, enabling self-validation and self-execution:

```php
// Create selection with model
$selection = FilterSelection::make('Active Users', User::class)
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

// Or fluently
$selection = FilterSelection::make()
    ->forModel(User::class)
    ->where(StatusFilter::class)->is('active');

// Model is included in JSON
$json = $selection->toJson();
```

**JSON output** (with model):

```json
{
  "model": "App\\Models\\User",
  "name": "Active Users",
  "filters": [
    {"filter": "StatusFilter", "mode": "is", "value": "active"},
    {"filter": "CountFilter", "mode": "gt", "value": 10}
  ]
}
```

#### Self-Validation

Validate the selection without needing to specify the model:

```php
// Load selection from JSON
$selection = FilterSelection::fromJson($json);

// Automatically validates against User model
$validation = $selection->validate();
// Returns: ['valid' => true, 'unknown' => [], 'known' => ['StatusFilter', 'CountFilter']]

if (!$validation['valid']) {
    // Handle unknown filters
    $unknownFilters = $validation['unknown'];
}
```

#### Self-Execution

Execute the selection directly without manually applying to a query:

```php
// Load and execute in one step
$selection = FilterSelection::fromJson($json);

// Get query builder
$query = $selection->query();
$results = $query->paginate(20);

// Or execute directly
$results = $selection->execute(); // Returns Collection
```

#### Checking Model

```php
$selection = FilterSelection::fromJson($json);

if ($selection->hasModel()) {
    $modelClass = $selection->getModelClass(); // "App\Models\User"
}
```

#### Backward Compatibility

The `model` field is **optional** - selections without it work as before:

```php
// Legacy JSON without model
$json = '{"filters": [{"filter": "StatusFilter", "mode": "is", "value": "active"}]}';

$selection = FilterSelection::fromJson($json);
$selection->hasModel(); // false

// Must manually apply to model
$users = User::query()->applySelection($selection)->get();
```

Methods that require a model throw clear exceptions:

```php
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active');

$selection->validate();  // LogicException: "Cannot validate without model class"
$selection->execute();   // LogicException: "Cannot create query without model class"
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
// Define presets with model
$presets = [
    'active_users' => FilterSelection::make('Active Users', User::class)
        ->where(StatusFilter::class)->is('active'),

    'high_value' => FilterSelection::make('High Value', User::class)
        ->where(StatusFilter::class)->is('active')
        ->where(CountFilter::class)->gt(100),

    'needs_attention' => FilterSelection::makeOr('Needs Attention', User::class)
        ->where(StatusFilter::class)->is('pending')
        ->where(StatusFilter::class)->is('review'),
];

// Store as JSON in database
$preset->json_config = $presets['high_value']->toJson();

// Load and execute directly (no need to specify model)
$selection = FilterSelection::fromJson($preset->json_config);
$users = $selection->execute(); // Automatically applies to User model
```

### API Filter Endpoint

```php
// POST /api/users/filter
public function filter(Request $request)
{
    $selection = FilterSelection::fromArray([
        'model' => User::class,
        'filters' => $request->input('filters', []),
    ]);

    // Validate filters
    $validation = $selection->validate();
    if (!$validation['valid']) {
        return response()->json([
            'error' => 'Invalid filters',
            'unknown' => $validation['unknown'],
        ], 422);
    }

    // Execute directly
    return $selection->query()->paginate();
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

## Database Persistence with FilterPreset

FilterCore includes an optional `FilterPreset` model for persisting filter selections to the database with user ownership and sharing capabilities.

### Publishing the Migration

```bash
php artisan vendor:publish --tag=filter-core-migrations
php artisan migrate
```

### Saving Selections

```php
use Ameax\FilterCore\Models\FilterPreset;

// Create a selection
$selection = FilterSelection::make('Active High-Value Users', User::class)
    ->description('Users with active status and high engagement')
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(100);

// Save as preset
$preset = FilterPreset::fromSelection(
    $selection,
    null,              // model type (optional - uses selection's model)
    auth()->id(),      // user_id (optional)
    false              // is_public (default: false)
);
```

### Loading and Executing

```php
// Find preset
$preset = FilterPreset::forModel(User::class)
    ->forUser(auth()->id())
    ->where('name', 'Active High-Value Users')
    ->first();

// Convert to selection and execute
$users = $preset->toSelection()->execute();

// Or use query() for pagination
$users = $preset->toSelection()->query()->paginate(20);
```

### Scopes

FilterPreset provides several useful query scopes:

```php
// By model
$presets = FilterPreset::forModel(User::class)->get();

// By user
$presets = FilterPreset::forUser(auth()->id())->get();

// Public presets
$presets = FilterPreset::public()->get();

// Accessible by user (owned or public)
$presets = FilterPreset::accessibleBy(auth()->id())->get();

// Combined
$presets = FilterPreset::forModel(User::class)
    ->accessibleBy(auth()->id())
    ->orderBy('name')
    ->get();
```

### Complete Workflow Example

```php
// 1. User creates and saves filter
public function saveFilter(Request $request)
{
    $selection = FilterSelection::make($request->input('name'), User::class)
        ->description($request->input('description'));

    // Build selection from request
    if ($request->filled('status')) {
        $selection->where(StatusFilter::class)->any($request->input('status'));
    }

    if ($request->filled('min_count')) {
        $selection->where(CountFilter::class)->gte($request->input('min_count'));
    }

    // Save preset
    $preset = FilterPreset::fromSelection(
        $selection,
        null,
        auth()->id(),
        $request->boolean('is_public')
    );

    return response()->json([
        'id' => $preset->id,
        'message' => 'Filter saved successfully',
    ]);
}

// 2. User loads saved filter
public function loadFilter(int $presetId)
{
    $preset = FilterPreset::accessibleBy(auth()->id())
        ->findOrFail($presetId);

    $users = $preset->toSelection()
        ->query()
        ->paginate(20);

    return response()->json([
        'preset' => [
            'id' => $preset->id,
            'name' => $preset->name,
            'description' => $preset->description,
        ],
        'users' => $users,
    ]);
}

// 3. List available presets
public function listPresets()
{
    $presets = FilterPreset::forModel(User::class)
        ->accessibleBy(auth()->id())
        ->orderBy('name')
        ->get(['id', 'name', 'description', 'is_public', 'user_id']);

    return response()->json($presets);
}
```

### Configuration

You can customize the user model in `config/filter-core.php`:

```php
return [
    'user_model' => \App\Models\CustomUser::class,
];
```

## Debugging Tools

FilterSelection provides several methods to debug and inspect the generated queries:

### SQL Preview

```php
$selection = FilterSelection::make()
    ->forModel(User::class)
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

// SQL with placeholders
$selection->toSql();
// → "select * from `users` where `status` = ? and `count` > ?"

// SQL with values interpolated (for debugging only!)
$selection->toSqlWithBindings();
// → "select * from `users` where `status` = 'active' and `count` > 10"
```

### Human-Readable Explanation

```php
$selection->explain();
// → "StatusFilter IS 'active' AND CountFilter GT 10"

// With nested groups
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->orWhere(function ($g) {
        $g->where(CountFilter::class)->gt(10);
        $g->where(CountFilter::class)->lt(100);
    });

$selection->explain();
// → "StatusFilter IS 'active' AND (CountFilter GT 10 OR CountFilter LT 100)"
```

### Full Debug Info

```php
$debug = $selection->debug();
// Returns:
// [
//     'sql' => 'select * from `users` where `status` = ? and `count` > ?',
//     'sql_with_bindings' => 'select * from `users` where `status` = \'active\' and `count` > 10',
//     'bindings' => ['active', 10],
//     'filters' => ['StatusFilter', 'CountFilter'],
//     'explanation' => 'StatusFilter IS \'active\' AND CountFilter GT 10'
// ]
```

### Dump and Die

```php
// For quick debugging
$selection->dd();
```

### Using with Provided Query

All debug methods accept an optional query parameter:

```php
// Without model class set
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active');

// Provide query explicitly
$selection->toSql(User::query());
$selection->toSqlWithBindings(User::query());
$selection->debug(User::query());
```

## Next Steps

- [Relation Filters](./05-relation-filters.md) - Filter through relationships
- [Collection Filtering](./06-collection-filtering.md) - Apply selections to collections
