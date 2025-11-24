# Filter Core - Quick Reference Guide

## One-Minute Overview

**Filter Core** is a Laravel filtering library that lets you:
- Define filters for database columns (SELECT, INTEGER, TEXT, BOOLEAN types)
- Apply them to Eloquent queries and Laravel Collections
- Group filters with AND/OR logic
- Persist filters as JSON
- Validate and sanitize values automatically

## Filter Types

```php
// SelectFilter - Choose from predefined options
class StatusFilter extends SelectFilter {
    public function column(): string { return 'status'; }
    public function options(): array { 
        return ['active' => 'Active', 'inactive' => 'Inactive'];
    }
}

// IntegerFilter - Numeric comparisons
class CountFilter extends IntegerFilter {
    public function column(): string { return 'count'; }
}

// TextFilter - Text searching
class NameFilter extends TextFilter {
    public function column(): string { return 'name'; }
}

// BooleanFilter - True/false values
class IsActiveFilter extends BooleanFilter {
    public function column(): string { return 'is_active'; }
}
```

## The 17 Match Modes

| Mode | Key | Usage | SQL |
|------|-----|-------|-----|
| IS | `is` | `.is('active')` | `=` |
| IS_NOT | `isNot` | `.isNot('deleted')` | `!=` |
| ANY | `any` | `.any(['a','b'])` | `IN` |
| ALL | `all` | `.all(['a','b'])` | All match |
| NONE | `none` | `.none(['x','y'])` | `NOT IN` |
| GT | `gt` | `.gt(10)` | `>` |
| GTE | `gte` | `.gte(10)` | `>=` |
| LT | `lt` | `.lt(100)` | `<` |
| LTE | `lte` | `.lte(100)` | `<=` |
| BETWEEN | `between` | `.between(10,100)` | `BETWEEN` |
| CONTAINS | `contains` | `.contains('search')` | `LIKE %...%` |
| STARTS_WITH | `startsWith` | `.startsWith('pre')` | `LIKE ...%` |
| ENDS_WITH | `endsWith` | `.endsWith('fix')` | `LIKE %...` |
| REGEX | `regex` | `.regex('^[A-Z]')` | `REGEXP` |
| EMPTY | `empty` | `.empty()` | `IS NULL` |
| NOT_EMPTY | `notEmpty` | `.notEmpty()` | `IS NOT NULL` |

## Basic Usage

```php
// Add trait to model
class User extends Model {
    use Filterable;
    
    protected static function filterResolver(): Closure {
        return fn() => [StatusFilter::class, CountFilter::class];
    }
}

// Single filter
User::query()->applyFilter(StatusFilter::value()->is('active'))->get();

// Multiple filters (AND logic)
User::query()->applyFilters([
    StatusFilter::value()->is('active'),
    CountFilter::value()->gt(10),
])->get();

// With selection (AND/OR logic)
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10)
    ->orWhere(fn($g) => $g->where(CountFilter::class)->gte(100));

User::query()->applySelection($selection)->get();
```

## FilterValue (Single Condition)

```php
// Builder syntax
FilterValue::for(StatusFilter::class)->is('active')

// Methods
->is($value)
->isNot($value)
->any(array)
->all(array)
->none(array)
->gt($value)
->gte($value)
->lt($value)
->lte($value)
->between($min, $max)
->contains($text)
->startsWith($text)
->endsWith($text)
->regex($pattern)
->empty()
->notEmpty()
->mode(customMode)
->value(rawValue)
```

## FilterSelection (Multiple Conditions)

```php
// Create with AND (default)
$selection = FilterSelection::make('My Filters')
    ->description('Filter description');

// Create with OR
$selection = FilterSelection::makeOr();

// Add filters
$selection->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10)
    ->orWhere(function($group) {
        $group->where(StatusFilter::class)->is('pending');
        $group->where(CountFilter::class)->gte(100);
    })
    ->andWhere(function($group) {
        // Another AND group
    });

// Query
$selection->hasFilters(): bool
$selection->has(StatusFilter::class): bool
$selection->get(StatusFilter::class): ?FilterValue
$selection->all(): array
$selection->count(): int

// Manage
$selection->remove(StatusFilter::class): self
$selection->clear(): self

// Serialize
$json = $selection->toJson()
$selection = FilterSelection::fromJson($json)
```

## Relation Filtering

```php
// Filter through relations
protected static function filterResolver(): Closure {
    return fn() => [
        // Records WITH matching relation
        PondTypeFilter::via('pond'),
        
        // Records WITHOUT matching relation
        PondTypeFilter::viaDoesntHave('pond'),
        
        // Records with NO relation at all
        PondTypeFilter::withoutRelation('pond'),
    ];
}
```

## Collection Filtering

```php
$collection = User::all();

// Single filter
User::filterCollectionWith($collection, 
    StatusFilter::value()->is('active')
);

// Multiple filters
User::filterCollection($collection, [
    StatusFilter::value()->is('active'),
    CountFilter::value()->gt(10),
]);

// With selection
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

User::filterCollectionWithSelection($collection, $selection);
```

## Dynamic Filters (At Runtime)

```php
protected static function filterResolver(): Closure {
    return fn() => [
        SelectFilter::dynamic('status')
            ->withColumn('status')
            ->withOptions(['active' => 'Active', 'inactive' => 'Inactive'])
            ->withLabel('Status'),
        
        IntegerFilter::dynamic('count')
            ->withColumn('item_count'),
        
        TextFilter::dynamic('name')
            ->withColumn('full_name')
            ->withNullable(true),
        
        BooleanFilter::dynamic('active')
            ->withColumn('is_active'),
    ];
}

// All dynamic filters support:
->withColumn(string)
->withLabel(string)
->withNullable(bool)
->withMeta(array)
->getKey(): string

// DynamicSelectFilter also:
->withOptions(array)
->withRelation(string)
```

## Value Sanitization & Validation

```php
// BooleanFilter auto-converts
'true', '1', 'yes', 'on', 1 → true
'false', '0', 'no', 'off', 0 → false

// IntegerFilter auto-converts
'10' → 10
['min'=>10, 'max'=>100] → BetweenValue(10, 100)

// TextFilter auto-trims
'  hello  ' → 'hello'

// Validation rules (Laravel)
class StatusFilter extends SelectFilter {
    public function validationRules(MatchModeContract $mode): array {
        return ['value' => ['required', Rule::in($this->options()->keys())]];
    }
}

// Type-safe values (strict PHP typing)
class TextFilter {
    public function typedValue(string $value): string { return $value; }
}

// Exception handling
try {
    $query->applyFilter($fv);
} catch (FilterValidationException $e) {
    $key = $e->getFilterKey();
    $errors = $e->getErrors();
    $messageBag = $e->getMessageBag();
}
```

## Advanced: Custom Match Mode

```php
class CaseInsensitiveMode implements MatchModeContract {
    public function key(): string { return 'caseInsensitive'; }
    
    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void {
        $query->whereRaw("LOWER($column) = ?", [strtolower($value)]);
    }
    
    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection {
        return $collection->filter(function($item) use($column, $value) {
            return strtolower(data_get($item, $column)) === strtolower($value);
        });
    }
}

// Register and use
MatchMode::register('caseInsensitive', CaseInsensitiveMode::class);
```

## Advanced: Custom Filter Logic

```php
class JSONFilter extends SelectFilter {
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool {
        // Custom logic
        if (!$mode instanceof IsMatchMode) {
            return false;  // Use default mode logic
        }
        
        $query->whereRaw("JSON_EXTRACT(data, '$.key') = ?", [$value]);
        return true;  // Custom logic applied
    }
}
```

## JSON Format

**Simple (flat AND)**:
```json
{
    "name": "My Filters",
    "filters": [
        {"filter": "StatusFilter", "mode": "is", "value": "active"},
        {"filter": "CountFilter", "mode": "gt", "value": 10}
    ]
}
```

**Complex (nested with OR)**:
```json
{
    "name": "My Filters",
    "group": {
        "operator": "and",
        "items": [
            {"filter": "StatusFilter", "mode": "is", "value": "active"},
            {
                "operator": "or",
                "items": [
                    {"filter": "CountFilter", "mode": "gt", "value": 10}
                ]
            }
        ]
    }
}
```

## Key Classes

| Class | Purpose |
|-------|---------|
| `Filter` | Base class for all filters |
| `SelectFilter` | For predefined options |
| `IntegerFilter` | For numeric values |
| `TextFilter` | For text searches |
| `BooleanFilter` | For true/false |
| `FilterValue` | Single filter condition |
| `FilterValueBuilder` | Fluent builder for FilterValue |
| `FilterSelection` | Group of filters |
| `FilterGroup` | AND/OR group |
| `QueryApplicator` | Apply to Eloquent queries |
| `CollectionApplicator` | Apply to Collections |
| `Filterable` | Model trait |
| `FilterDefinition` | Filter metadata |
| `BetweenValue` | Range value DTO |
| `FilterValidationException` | Validation error |

## Enums

```php
FilterTypeEnum::SELECT      // Predefined options
FilterTypeEnum::INTEGER     // Numbers
FilterTypeEnum::TEXT        // Text
FilterTypeEnum::BOOLEAN     // True/false

GroupOperatorEnum::AND      // All conditions must match
GroupOperatorEnum::OR       // At least one condition must match

RelationModeEnum::HAS           // whereHas()
RelationModeEnum::DOESNT_HAVE   // whereDoesntHave()
RelationModeEnum::HAS_NONE      // whereDoesntHave() without condition
```

## Tips

1. Always define `filterResolver()` in your model to enable filtering
2. Use the Filterable trait to add scope methods to your model
3. Use FilterSelection for complex AND/OR logic
4. Use JSON serialization to persist filter configurations
5. Override `sanitizeValue()` for custom value conversion
6. Override `validationRules()` for custom validation
7. Override `apply()` for custom query logic
8. Use dynamic filters when you don't want to create a class
9. Relations can be filtered using `via()`, `viaDoesntHave()`, `withoutRelation()`
10. Test with both Query and Collection filtering

