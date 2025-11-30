# Dynamic Filters

Create filters at runtime without defining filter classes. Useful for configuration-driven UIs, admin panels, and dynamic filter sets.

## Overview

Dynamic filters provide the same functionality as class-based filters but are configured at runtime instead of in PHP classes.

**When to use dynamic filters:**
- Filter configuration stored in database
- Admin-configurable filters
- Generated from API schemas
- Temporary or one-off filters

**When to use class-based filters:**
- Reusable across the application
- Need custom logic (override `apply()`)
- Type-safe with IDE autocompletion
- Complex validation rules

## Creating Dynamic Filters

### SelectFilter

```php
use Ameax\FilterCore\Filters\SelectFilter;

$statusFilter = SelectFilter::dynamic('status_filter')
    ->withColumn('status')
    ->withLabel('User Status')
    ->withOptions([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
    ]);
```

### IntegerFilter

```php
use Ameax\FilterCore\Filters\IntegerFilter;

$countFilter = IntegerFilter::dynamic('count_filter')
    ->withColumn('count')
    ->withLabel('Item Count');
```

### TextFilter

```php
use Ameax\FilterCore\Filters\TextFilter;

$nameFilter = TextFilter::dynamic('name_filter')
    ->withColumn('name')
    ->withLabel('Name');
```

### BooleanFilter

```php
use Ameax\FilterCore\Filters\BooleanFilter;

$activeFilter = BooleanFilter::dynamic('is_active')
    ->withColumn('is_active')
    ->withLabel('Is Active');
```

### DecimalFilter

```php
use Ameax\FilterCore\Filters\DecimalFilter;

// Basic decimal filter
$priceFilter = DecimalFilter::dynamic('price')
    ->withColumn('price')
    ->withLabel('Price')
    ->withPrecision(2)
    ->withMin(0.0)
    ->withMax(999999.99);

// For columns storing decimals as integers (cents pattern)
$priceCentsFilter = DecimalFilter::dynamic('price_cents')
    ->withColumn('price_cents')
    ->withLabel('Price')
    ->withPrecision(2)
    ->withStoredAsInteger(true); // 19.99 → queries as 1999
```

### DateFilter

```php
use Ameax\FilterCore\Filters\DateFilter;

// Basic date filter
$dateFilter = DateFilter::dynamic('created_at')
    ->withColumn('created_at')
    ->withLabel('Created Date');

// Past-only filter (for birth dates, etc.)
$birthFilter = DateFilter::dynamic('birth_date')
    ->withColumn('birth_date')
    ->withLabel('Birth Date')
    ->withPastOnly();

// Future-only filter (for due dates, etc.)
$dueFilter = DateFilter::dynamic('due_date')
    ->withColumn('due_at')
    ->withLabel('Due Date')
    ->withFutureOnly()
    ->withAllowToday(true);
```

See [Date Filter](./10-date-filter.md) for comprehensive date filtering documentation.

## Configuration Methods

### Required

```php
$filter = SelectFilter::dynamic('unique_key')  // Unique identifier
    ->withColumn('column_name');                // Database column
```

### Optional

```php
$filter = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withLabel('Status Label')              // UI label
    ->withOptions(['a' => 'A', 'b' => 'B'])  // For SelectFilter
    ->withNullable(true)                     // Allow empty/notEmpty modes
    ->withRelation('company')                // For relation filtering
    ->withMeta(['icon' => 'user']);          // Custom metadata
```

## Using Dynamic Filters

### With QueryApplicator

```php
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\MatchModes\IsMatchMode;

$statusFilter = SelectFilter::dynamic('status')->withColumn('status');
$countFilter = IntegerFilter::dynamic('count')->withColumn('count');

$result = QueryApplicator::for(User::query())
    ->withFilters([$statusFilter, $countFilter])
    ->applyFilters([
        new FilterValue('status', new IsMatchMode(), 'active'),
        new FilterValue('count', MatchMode::gt(), 10),
    ])
    ->getQuery()
    ->get();
```

### Combining with Static Filters

```php
$dynamicFilter = IntegerFilter::dynamic('custom_count')
    ->withColumn('count');

$result = QueryApplicator::for(User::query())
    ->withFilters([
        StatusFilter::class,    // Static (from class)
        $dynamicFilter,         // Dynamic (runtime)
    ])
    ->applyFilters([
        FilterValue::for(StatusFilter::class)->is('active'),
        new FilterValue('custom_count', MatchMode::gt(), 50),
    ])
    ->getQuery()
    ->get();
```

## Dynamic Relation Filters

```php
$companyStatusFilter = SelectFilter::dynamic('company_status')
    ->withColumn('status')
    ->withRelation('company')  // Uses whereHas()
    ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']);

$result = QueryApplicator::for(User::query())
    ->withFilters([$companyStatusFilter])
    ->applyFilter(new FilterValue('company_status', MatchMode::is(), 'active'))
    ->getQuery()
    ->get();
```

## Building Filters from Configuration

### From Array/Database

```php
// Filter configuration (could come from database)
$filterConfigs = [
    [
        'key' => 'status',
        'type' => 'select',
        'column' => 'status',
        'label' => 'Status',
        'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
    ],
    [
        'key' => 'count',
        'type' => 'integer',
        'column' => 'count',
        'label' => 'Count',
    ],
    [
        'key' => 'price',
        'type' => 'decimal',
        'column' => 'price',
        'label' => 'Price',
        'precision' => 2,
        'min' => 0.0,
    ],
    [
        'key' => 'name',
        'type' => 'text',
        'column' => 'name',
        'label' => 'Name',
    ],
    [
        'key' => 'is_active',
        'type' => 'boolean',
        'column' => 'is_active',
        'label' => 'Is Active',
    ],
    [
        'key' => 'created_at',
        'type' => 'date',
        'column' => 'created_at',
        'label' => 'Created Date',
        'directions' => ['past'], // Optional: restrict to past only
    ],
];

// Build filters from config
$filters = [];
foreach ($filterConfigs as $config) {
    $filter = match ($config['type']) {
        'select' => SelectFilter::dynamic($config['key'])
            ->withColumn($config['column'])
            ->withLabel($config['label'] ?? $config['key'])
            ->withOptions($config['options'] ?? []),

        'integer' => IntegerFilter::dynamic($config['key'])
            ->withColumn($config['column'])
            ->withLabel($config['label'] ?? $config['key']),

        'decimal' => DecimalFilter::dynamic($config['key'])
            ->withColumn($config['column'])
            ->withLabel($config['label'] ?? $config['key'])
            ->withPrecision($config['precision'] ?? 2)
            ->withMin($config['min'] ?? null)
            ->withMax($config['max'] ?? null)
            ->withStoredAsInteger($config['stored_as_integer'] ?? false),

        'text' => TextFilter::dynamic($config['key'])
            ->withColumn($config['column'])
            ->withLabel($config['label'] ?? $config['key']),

        'boolean' => BooleanFilter::dynamic($config['key'])
            ->withColumn($config['column'])
            ->withLabel($config['label'] ?? $config['key']),

        'date' => DateFilter::dynamic($config['key'])
            ->withColumn($config['column'])
            ->withLabel($config['label'] ?? $config['key'])
            ->withAllowedDirections(
                isset($config['directions'])
                    ? array_map(fn($d) => DateDirection::from($d), $config['directions'])
                    : null
            ),

        default => throw new \InvalidArgumentException("Unknown filter type: {$config['type']}"),
    };

    $filters[] = $filter;
}

// Use the filters
$result = QueryApplicator::for(User::query())
    ->withFilters($filters)
    ->applyFilters($filterValues)
    ->getQuery()
    ->get();
```

### Filter Builder Helper

```php
class FilterBuilder
{
    public static function fromConfig(array $config): Filter
    {
        $filter = match ($config['type']) {
            'select' => SelectFilter::dynamic($config['key']),
            'integer' => IntegerFilter::dynamic($config['key']),
            'decimal' => DecimalFilter::dynamic($config['key']),
            'text' => TextFilter::dynamic($config['key']),
            'boolean' => BooleanFilter::dynamic($config['key']),
            'date' => DateFilter::dynamic($config['key']),
            default => throw new \InvalidArgumentException("Unknown type: {$config['type']}"),
        };

        $filter->withColumn($config['column']);

        if (isset($config['label'])) {
            $filter->withLabel($config['label']);
        }

        if (isset($config['options']) && method_exists($filter, 'withOptions')) {
            $filter->withOptions($config['options']);
        }

        if (isset($config['relation'])) {
            $filter->withRelation($config['relation']);
        }

        if (isset($config['nullable'])) {
            $filter->withNullable($config['nullable']);
        }

        // Date-specific configuration
        if (isset($config['directions']) && method_exists($filter, 'withAllowedDirections')) {
            $directions = array_map(fn($d) => DateDirection::from($d), $config['directions']);
            $filter->withAllowedDirections($directions);
        }

        return $filter;
    }

    public static function fromConfigs(array $configs): array
    {
        return array_map([self::class, 'fromConfig'], $configs);
    }
}

// Usage
$filters = FilterBuilder::fromConfigs($filterConfigs);
```

## Dynamic Filters in API Endpoints

```php
class FilterController extends Controller
{
    public function filter(Request $request)
    {
        // Load filter configuration from database
        $filterConfigs = FilterConfig::where('model', 'users')->get();

        // Build filters
        $filters = $filterConfigs->map(fn($config) =>
            FilterBuilder::fromConfig($config->toArray())
        )->all();

        // Parse filter values from request
        $filterValues = collect($request->input('filters', []))
            ->map(fn($f) => new FilterValue(
                $f['filter'],
                MatchMode::get($f['mode']),
                $f['value']
            ))
            ->all();

        // Apply filters
        $query = QueryApplicator::for(User::query())
            ->withFilters($filters)
            ->applyFilters($filterValues)
            ->getQuery();

        return $query->paginate();
    }
}
```

## FilterDefinition

Dynamic filters can be converted to `FilterDefinition` for serialization:

```php
$filter = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withLabel('Status')
    ->withOptions(['a' => 'A', 'b' => 'B']);

// Convert to definition
$definition = $filter->toDefinition();

$definition->getKey();      // 'status'
$definition->getColumn();   // 'status'
$definition->getLabel();    // 'Status'
$definition->getType();     // FilterTypeEnum::SELECT
$definition->getOptions();  // ['a' => 'A', 'b' => 'B']
```

## Allowed Match Modes

Dynamic filters inherit allowed modes from their base type:

| Dynamic Type | Allowed Modes |
|--------------|---------------|
| `SelectFilter::dynamic()` | `is`, `isNot`, `any`, `all`, `none` |
| `IntegerFilter::dynamic()` | `is`, `isNot`, `gt`, `gte`, `lt`, `lte`, `between` |
| `DecimalFilter::dynamic()` | `is`, `isNot`, `any`, `none`, `gt`, `gte`, `lt`, `lte`, `between` |
| `TextFilter::dynamic()` | `is`, `isNot`, `contains`, `startsWith`, `endsWith`, `regex` |
| `BooleanFilter::dynamic()` | `is` |
| `DateFilter::dynamic()` | `dateRange`, `notInDateRange` |

With `withNullable(true)`, all types also support `empty` and `notEmpty`.

## Metadata

Attach custom metadata for UI purposes:

```php
$filter = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withMeta([
        'icon' => 'status-icon',
        'group' => 'user-filters',
        'priority' => 1,
        'helpText' => 'Filter users by their current status',
    ]);

// Access metadata
$meta = $filter->getMeta();
$icon = $meta['icon']; // 'status-icon'
```

## Next Steps

- [Date Filter](./10-date-filter.md) - Date/datetime filtering with ranges
- [Validation & Sanitization](./08-validation-sanitization.md) - Input processing
- [Filter Types](./02-filter-types.md) - Class-based filter definitions
