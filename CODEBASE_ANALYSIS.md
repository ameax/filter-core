# Filter Core - Comprehensive Codebase Analysis

## Project Overview

**Filter Core** is a powerful, type-safe filtering system for Laravel applications that provides a clean API for building complex database queries with automatic value sanitization, validation, and support for relations.

- **Language**: PHP 8.1+
- **Framework**: Laravel (Eloquent ORM)
- **Architecture**: Modular, plug-and-play
- **Test Coverage**: 5,278 lines of test code across multiple test files
- **Status**: Fully implemented with all core features

---

## Directory Structure

```
src/
├── Collection/
│   └── CollectionApplicator.php          # Apply filters to in-memory Laravel Collections
├── Commands/
│   └── FilterCoreCommand.php             # Artisan command
├── Concerns/
│   └── Filterable.php                    # Model trait for filter integration
├── Contracts/
│   └── MatchModeContract.php             # Interface for match modes
├── Data/
│   ├── FilterValue.php                   # Single filter condition (filter + mode + value)
│   ├── FilterValueBuilder.php            # Fluent builder for FilterValue
│   ├── FilterDefinition.php              # Metadata about a filter
│   └── BetweenValue.php                  # DTO for BETWEEN mode values
├── Enums/
│   ├── FilterTypeEnum.php                # SELECT, INTEGER, TEXT, BOOLEAN
│   ├── GroupOperatorEnum.php             # AND, OR logic
│   └── RelationModeEnum.php              # HAS, DOESNT_HAVE, HAS_NONE
├── Exceptions/
│   └── FilterValidationException.php     # Validation error handling
├── Facades/
│   └── FilterCore.php                    # Facade (placeholder)
├── Filters/
│   ├── Filter.php                        # Abstract base class for all filters
│   ├── SelectFilter.php                  # For SELECT type filters
│   ├── IntegerFilter.php                 # For INTEGER type filters
│   ├── TextFilter.php                    # For TEXT type filters
│   ├── BooleanFilter.php                 # For BOOLEAN type filters
│   ├── HasOptions.php                    # Interface for filters with selectable options
│   └── Dynamic/                          # Runtime-defined filters
│       ├── DynamicFilter.php             # Interface for dynamic filters
│       ├── DynamicSelectFilter.php
│       ├── DynamicTextFilter.php
│       ├── DynamicIntegerFilter.php
│       └── DynamicBooleanFilter.php
├── MatchModes/                           # 17 match modes + factory
│   ├── MatchMode.php                     # Factory class for creating match modes
│   ├── IsMatchMode.php                   # Exact match
│   ├── IsNotMatchMode.php                # Not equal
│   ├── AnyMatchMode.php                  # At least one value matches (OR)
│   ├── AllMatchMode.php                  # All values must match
│   ├── NoneMatchMode.php                 # No value may match
│   ├── GreaterThanMatchMode.php          # > operator
│   ├── GreaterThanOrEqualMatchMode.php   # >= operator
│   ├── LessThanMatchMode.php             # < operator
│   ├── LessThanOrEqualMatchMode.php      # <= operator
│   ├── BetweenMatchMode.php              # BETWEEN min AND max
│   ├── ContainsMatchMode.php             # LIKE %value%
│   ├── StartsWithMatchMode.php           # LIKE value%
│   ├── EndsWithMatchMode.php             # LIKE %value
│   ├── RegexMatchMode.php                # REGEXP pattern matching
│   ├── EmptyMatchMode.php                # IS NULL
│   └── NotEmptyMatchMode.php             # IS NOT NULL
├── Query/
│   └── QueryApplicator.php               # Apply filters to Eloquent queries
└── Selections/
    ├── FilterSelection.php               # Collection of filters with AND/OR logic
    ├── FilterGroup.php                   # Nested group of filters
    └── FilterGroupBuilder.php            # Fluent builder for FilterGroup
```

---

## Core Concepts

### 1. Filter Types (4 Built-in Types)

#### **SelectFilter** - For predefined options

```php
class StatusFilter extends SelectFilter
{
    public function column(): string { return 'status'; }
    public function options(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}
```

**Allowed Match Modes**: IS, IS_NOT, ANY, ALL, NONE
**Default Match Mode**: IS

#### **IntegerFilter** - For numeric comparisons

```php
class CountFilter extends IntegerFilter
{
    public function column(): string { return 'count'; }
}
```

**Allowed Match Modes**: IS, IS_NOT, GT, GTE, LT, LTE, BETWEEN
**Default Match Mode**: IS
**Special**: Handles BetweenValue DTO for range queries

#### **TextFilter** - For text searching

```php
class NameFilter extends TextFilter
{
    public function column(): string { return 'name'; }
}
```

**Allowed Match Modes**: CONTAINS, STARTS_WITH, ENDS_WITH, REGEX, IS, IS_NOT
**Default Match Mode**: CONTAINS
**Special**: Automatic trimming of input values

#### **BooleanFilter** - For yes/no values

```php
class IsActiveFilter extends BooleanFilter
{
    public function column(): string { return 'is_active'; }
}
```

**Allowed Match Modes**: IS
**Default Match Mode**: IS
**Special**: Automatic conversion of truthy/falsy values (true, '1', 'yes', 'on' → true; false, '0', 'no', 'off' → false)

---

### 2. Filter Base Class (Filter.php)

**Main Methods**:
- `column(): string` - The database column this filter operates on
- `type(): FilterTypeEnum` - The filter type (SELECT, INTEGER, TEXT, BOOLEAN)
- `allowedModes(): array` - Which match modes are allowed
- `defaultMode(): MatchModeContract` - The default match mode
- `label(): string` - Human-readable label (defaults to class name)
- `nullable(): bool` - Whether the column can be NULL
- `meta(): array` - Additional metadata
- `sanitizeValue(mixed $value, MatchModeContract $mode): mixed` - Normalize/convert values
- `validationRules(MatchModeContract $mode): array` - Laravel validation rules
- `apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool` - Custom query logic (override for custom handling)

**Static Methods**:
- `make(): self` - Create an instance
- `key(): string` - Get the filter class name (used as unique key)
- `value(): FilterValueBuilder` - Start building a FilterValue
- `via(string $relation): self` - Create filter that applies through a relation (whereHas)
- `viaDoesntHave(string $relation): self` - Filter for records that DON'T have matching relation
- `withoutRelation(string $relation): self` - Filter for records without any relation

**Example Implementation**:
```php
class KoiStatusFilter extends SelectFilter
{
    public function column(): string
    {
        return 'status';
    }

    public function options(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
        ];
    }

    public function label(): string
    {
        return 'Koi Status';
    }
}
```

---

### 3. Match Modes (17 Total)

Each match mode implements `MatchModeContract`:
- `key(): string` - Unique identifier (used for serialization)
- `apply(Builder|QueryBuilder $query, string $column, mixed $value): void` - Apply to Eloquent query
- `applyToCollection(Collection $collection, string $column, mixed $value): Collection` - Apply to Laravel Collection

#### **Equality Modes**

| Mode | Key | SQL | Example |
|------|-----|-----|---------|
| IS | `is` | `WHERE column = value` or `WHERE column IN (...)` | `.is('active')` |
| IS_NOT | `isNot` / `is_not` | `WHERE column != value` or `WHERE column NOT IN (...)` | `.isNot('deleted')` |

#### **Multi-Value Modes**

| Mode | Key | SQL | Example |
|------|-----|-----|---------|
| ANY | `any` | `WHERE column IN (...)` | `.any(['a', 'b'])` |
| ALL | `all` | For regular columns: single value match; for JSON: all values present | `.all(['a', 'b'])` |
| NONE | `none` | `WHERE column NOT IN (...)` | `.none(['x', 'y'])` |

#### **Comparison Modes**

| Mode | Key | SQL | Example |
|------|-----|-----|---------|
| GREATER_THAN | `gt` | `WHERE column > value` | `.gt(10)` |
| GREATER_THAN_OR_EQUAL | `gte` | `WHERE column >= value` | `.gte(10)` |
| LESS_THAN | `lt` | `WHERE column < value` | `.lt(100)` |
| LESS_THAN_OR_EQUAL | `lte` | `WHERE column <= value` | `.lte(100)` |
| BETWEEN | `between` | `WHERE column BETWEEN min AND max` | `.between(10, 100)` |

#### **Text Search Modes**

| Mode | Key | SQL | Example |
|------|-----|-----|---------|
| CONTAINS | `contains` | `WHERE column LIKE %value%` | `.contains('search')` |
| STARTS_WITH | `startsWith` / `starts_with` | `WHERE column LIKE value%` | `.startsWith('pre')` |
| ENDS_WITH | `endsWith` / `ends_with` | `WHERE column LIKE %value` | `.endsWith('fix')` |
| REGEX | `regex` | `WHERE column REGEXP pattern` | `.regex('^[A-Z]')` |

#### **Null Check Modes**

| Mode | Key | SQL | Example |
|------|-----|-----|---------|
| EMPTY | `empty` | `WHERE column IS NULL` | `.empty()` |
| NOT_EMPTY | `notEmpty` / `not_empty` | `WHERE column IS NOT NULL` | `.notEmpty()` |

**Match Mode Factory** (MatchMode.php):
```php
// Static factory methods (auto-converted from camelCase)
MatchMode::is()           // IsMatchMode
MatchMode::isNot()        // IsNotMatchMode
MatchMode::any()          // AnyMatchMode
MatchMode::gt()           // GreaterThanMatchMode
MatchMode::contains()     // ContainsMatchMode
// ... etc

// Register custom modes
MatchMode::register('myMode', MyCustomMatchMode::class);
```

---

### 4. FilterValue & FilterValueBuilder

**FilterValue**: Represents a single filter condition (filter key + match mode + value)

```php
// Create with builder (fluent API)
FilterValue::for(StatusFilter::class)->is('active')
FilterValue::for(CountFilter::class)->gt(10)
FilterValue::for(NameFilter::class)->contains('search')

// Or direct instantiation
new FilterValue('StatusFilter', new IsMatchMode, 'active')

// Methods
$fv->getFilterKey(): string
$fv->getMatchMode(): MatchModeContract
$fv->getValue(): mixed
$fv->withValue($newValue): FilterValue
$fv->withMatchMode($newMode): FilterValue

// Serialization
$array = $fv->toArray()  // ['filter' => 'StatusFilter', 'mode' => 'is', 'value' => 'active']
$json = json_encode($fv)
FilterValue::fromArray($array)  // Deserialize
```

**FilterValueBuilder**: Fluent builder for creating FilterValue instances

```php
FilterValue::for(StatusFilter::class)
    ->is('active')              // Sets IS match mode
    ->isNot('deleted')          // Sets IS_NOT
    ->any(['a', 'b'])           // Sets ANY
    ->all(['a', 'b'])           // Sets ALL
    ->none(['x', 'y'])          // Sets NONE
    ->gt(10)                    // Greater than
    ->gte(10)                   // Greater or equal
    ->lt(100)                   // Less than
    ->lte(100)                  // Less or equal
    ->between(10, 100)          // Between (min, max)
    ->contains('text')          // Contains
    ->startsWith('pre')         // Starts with
    ->endsWith('fix')           // Ends with
    ->regex('^[A-Z]')          // Regex pattern
    ->empty()                   // IS NULL
    ->notEmpty()                // IS NOT NULL
    ->mode(customMode)          // Custom match mode
    ->value(rawValue)           // Raw value

// Returns FilterValue (or FilterSelection if called within selection context)
```

---

### 5. FilterSelection & FilterGroup

**FilterSelection**: Top-level container for filters with AND/OR logic

```php
// Create with default AND logic
$selection = FilterSelection::make('My Filters')
    ->description('Filter description');

// Create with OR logic at top level
$selection = FilterSelection::makeOr();

// Add filters (fluent API)
$selection
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

// Add OR groups
$selection
    ->where(StatusFilter::class)->is('active')
    ->orWhere(function ($group) {
        $group->where(StatusFilter::class)->is('pending');
        $group->where(CountFilter::class)->gte(100);
    });

// Add AND groups
$selection
    ->andWhere(function ($group) {
        $group->where(StatusFilter::class)->is('active');
        $group->where(CountFilter::class)->gt(5);
    });

// Query methods
$selection->hasFilters(): bool
$selection->has(StatusFilter::class): bool
$selection->get(StatusFilter::class): ?FilterValue
$selection->count(): int
$selection->all(): array<FilterValue>
$selection->hasNestedGroups(): bool
$selection->getGroup(): FilterGroup
$selection->getOperator(): GroupOperatorEnum

// Management
$selection->add(FilterValue $fv): self
$selection->addGroup(FilterGroup $group): self
$selection->remove(StatusFilter::class): self
$selection->clear(): self

// Metadata
$selection->name($name): self
$selection->description($desc): self
$selection->getName(): ?string
$selection->getDescription(): ?string

// Serialization
$json = $selection->toJson()
$array = $selection->toArray()
FilterSelection::fromJson($json)
FilterSelection::fromArray($array)
```

**FilterGroup**: Represents a group of filters with AND or OR operator

```php
// Create groups
$andGroup = FilterGroup::and();
$orGroup = FilterGroup::or();

// Add items
$group->add(FilterValue $fv): self
$group->addGroup(FilterGroup $nestedGroup): self

// Build with fluent API
$group->where(StatusFilter::class)->is('active')
$group->orWhere(function ($g) { ... })
$group->andWhere(function ($g) { ... })

// Query methods
$group->getOperator(): GroupOperatorEnum
$group->getItems(): array
$group->isEmpty(): bool
$group->count(): int
$group->hasNestedGroups(): bool
$group->getAllFilterValues(): array<FilterValue>  // Flatten all filters
$group->getAllFilterKeys(): array<string>

// Serialization
$array = $group->toArray()
FilterGroup::fromArray($array)
```

**JSON Serialization Formats**:

Simple (AND logic, no nesting):
```json
{
    "name": "My Filters",
    "description": "...",
    "filters": [
        {"filter": "StatusFilter", "mode": "is", "value": "active"},
        {"filter": "CountFilter", "mode": "gt", "value": 10}
    ]
}
```

Complex (OR logic, nested groups):
```json
{
    "name": "My Filters",
    "description": "...",
    "group": {
        "operator": "and",
        "items": [
            {"filter": "StatusFilter", "mode": "is", "value": "active"},
            {
                "operator": "or",
                "items": [
                    {"filter": "CountFilter", "mode": "gt", "value": 10},
                    {"filter": "NameFilter", "mode": "contains", "value": "search"}
                ]
            }
        ]
    }
}
```

---

### 6. Relation Filtering (RelationModeEnum)

Filters can be applied through Eloquent relations:

```php
// In Model filterResolver():
protected static function filterResolver(): Closure
{
    return fn () => [
        // Filter records that HAVE a matching relation
        PondWaterTypeFilter::via('pond'),
        
        // Filter records that DON'T HAVE a matching relation
        PondWaterTypeFilter::viaDoesntHave('pond'),
        
        // Filter records WITHOUT any relation
        SomeFilter::withoutRelation('pond'),
    ];
}
```

**RelationModeEnum Values**:
- `HAS` - Use `whereHas()` for records with matching relation
- `DOESNT_HAVE` - Use `whereDoesntHave()` for records without matching relation
- `HAS_NONE` - Use `whereDoesntHave()` without condition for records with no relation at all

**Example SQL Generated**:
```sql
-- via('pond')
WHERE EXISTS (SELECT * FROM ponds WHERE koi.pond_id = pond.id AND pond.water_type = 'fresh')

-- viaDoesntHave('pond')
WHERE NOT EXISTS (SELECT * FROM ponds WHERE koi.pond_id = pond.id AND pond.water_type = 'fresh')

-- withoutRelation('pond')
WHERE NOT EXISTS (SELECT * FROM ponds WHERE koi.pond_id = pond.id)
```

---

### 7. Dynamic Filters

Create filters at runtime without defining a class:

```php
// SelectFilter
SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withLabel('Status')
    ->withOptions(['active' => 'Active', 'inactive' => 'Inactive'])
    ->withNullable(true)
    ->withMeta(['icon' => 'circle'])

// IntegerFilter
IntegerFilter::dynamic('count')
    ->withColumn('item_count')
    ->withLabel('Item Count')
    ->withNullable(false)

// TextFilter
TextFilter::dynamic('name')
    ->withColumn('full_name')
    ->withLabel('Full Name')

// BooleanFilter
BooleanFilter::dynamic('active')
    ->withColumn('is_active')
    ->withLabel('Is Active')

// Use in filterResolver()
protected static function filterResolver(): Closure
{
    return fn () => [
        StatusFilter::class,
        IntegerFilter::dynamic('count')->withColumn('item_count'),
    ];
}
```

**All Dynamic Filters Support**:
- `withColumn(string $column)` - Database column name
- `withLabel(string $label)` - Human-readable label
- `withNullable(bool $nullable)` - Allow NULL values
- `withMeta(array $meta)` - Additional metadata
- `getKey(): string` - Get the filter's unique key

**Dynamic Select Filter Also Supports**:
- `withOptions(array $options)` - Available options
- `withRelation(string $relation)` - Relation name

---

### 8. The Filterable Trait

Add to models to enable filtering:

```php
use Ameax\FilterCore\Concerns\Filterable;

class Koi extends Model
{
    use Filterable;

    // Define available filters (lazy-loaded)
    protected static function filterResolver(): Closure
    {
        return fn () => [
            KoiStatusFilter::class,
            KoiCountFilter::class,
            PondWaterTypeFilter::via('pond'),  // Relation filter
        ];
    }
}
```

**Key Methods**:
```php
// Query filtering
$query->applyFilter(FilterValue $fv): Builder
$query->applyFilters(array<FilterValue>|FilterSelection $filters): Builder
$query->applySelection(FilterSelection $selection, bool $strict = true): Builder

// Collection filtering
Koi::filterCollection(Collection $collection, array|FilterSelection $filters): Collection
Koi::filterCollectionWith(Collection $collection, FilterValue $fv): Collection
Koi::filterCollectionWithSelection(Collection $collection, FilterSelection $selection): Collection

// Metadata
Koi::getFilters(): array<Filter>
Koi::getFilterByKey(string $key): ?Filter
Koi::getFilterKeys(): array<string>
Koi::validateSelection(FilterSelection $selection): array{valid, unknown, known}
Koi::clearFilterCache(): void
```

---

### 9. QueryApplicator

Apply filters to Eloquent queries:

```php
use Ameax\FilterCore\Query\QueryApplicator;

$result = QueryApplicator::for(Koi::query())
    ->withFilters([KoiStatusFilter::class, KoiCountFilter::class])
    ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
    ->applyFilter(FilterValue::for(KoiCountFilter::class)->gt(10))
    ->getQuery()
    ->get();

// Or with FilterSelection
$selection = FilterSelection::make()
    ->where(KoiStatusFilter::class)->is('active')
    ->where(KoiCountFilter::class)->gt(10);

$result = QueryApplicator::for(Koi::query())
    ->withFilters([KoiStatusFilter::class, KoiCountFilter::class])
    ->applySelection($selection)
    ->getQuery()
    ->get();

// Get applied filters
QueryApplicator::for($query)
    ->withFilters($filters)
    ->applyFilters($filterValues)
    ->getAppliedFilters(): array<FilterValue>
    ->hasAppliedFilters(): bool
```

**Key Features**:
- Automatic value sanitization via `Filter::sanitizeValue()`
- Type checking via `Filter::typedValue()` (with TypeError handling)
- Validation via `Filter::validationRules()` (throws FilterValidationException)
- Custom filter logic via `Filter::apply()` override
- Relation filtering (whereHas, whereDoesntHave)
- Nested FilterGroup support (AND/OR logic)
- BetweenValue handling for range queries

---

### 10. CollectionApplicator

Apply filters to Laravel Collections:

```php
use Ameax\FilterCore\Collection\CollectionApplicator;

$collection = Koi::all();

// Single filter
$filtered = CollectionApplicator::for($collection)
    ->withFilters([KoiStatusFilter::class])
    ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
    ->getCollection();

// Multiple filters
$filtered = CollectionApplicator::for($collection)
    ->withFilters([KoiStatusFilter::class, KoiCountFilter::class])
    ->applyFilters([
        FilterValue::for(KoiStatusFilter::class)->is('active'),
        FilterValue::for(KoiCountFilter::class)->gt(10),
    ])
    ->getCollection();

// With FilterSelection (including OR logic)
$selection = FilterSelection::makeOr()
    ->where(KoiStatusFilter::class)->is('active')
    ->where(KoiStatusFilter::class)->is('pending');

$filtered = CollectionApplicator::for($collection)
    ->withFilters([KoiStatusFilter::class])
    ->applySelection($selection)
    ->getCollection();

// Via Filterable trait
$filtered = Koi::filterCollection($collection, $filterValues);
$filtered = Koi::filterCollectionWithSelection($collection, $selection);
```

**Key Features**:
- Same sanitization & validation as QueryApplicator
- AND/OR logic via FilterGroups
- Uses Laravel Collection methods for filtering
- Supports nested FilterGroups

---

### 11. Value Sanitization & Validation

**Sanitization** - Automatic type conversion before validation:

```php
// BooleanFilter: converts truthy/falsy values
'true' → true, '1' → true, 'yes' → true, 'on' → true, 1 → true
'false' → false, '0' → false, 'no' → false, 'off' → false, 0 → false

// IntegerFilter: converts to int or BetweenValue
'10' → 10
['min' => 10, 'max' => 100] → BetweenValue(min: 10, max: 100)

// TextFilter: trims whitespace
'  search  ' → 'search'
```

**Validation** - Laravel validation rules:

```php
class StatusFilter extends SelectFilter
{
    public function validationRules(MatchModeContract $mode): array
    {
        // ANY, ALL, NONE modes expect arrays
        if (in_array($mode->key(), ['any', 'all', 'none'])) {
            return [
                'value' => 'required|array',
                'value.*' => Rule::in($this->options()->keys()),
            ];
        }

        // IS, IS_NOT expect single value
        return [
            'value' => ['required', Rule::in($this->options()->keys())],
        ];
    }
}

class NameFilter extends TextFilter
{
    public function validationRules(MatchModeContract $mode): array
    {
        return ['value' => 'required|string'];
    }
}
```

**Typed Values** - Strict type checking:

```php
class TextFilter
{
    public function typedValue(string $value): string
    {
        return $value;  // PHP type system enforces string
    }
}

class IntegerFilter
{
    public function typedValue(int|BetweenValue $value): int|BetweenValue
    {
        return $value;
    }
}

// Usage - throws TypeError if wrong type
$filter->typedValue(123)        // ✓ Works
$filter->typedValue('string')   // ✗ TypeError
```

**Validation Exception**:

```php
try {
    QueryApplicator::for($query)
        ->withFilters($filters)
        ->applyFilter($invalidFilter)
        ->getQuery()
        ->get();
} catch (FilterValidationException $e) {
    $key = $e->getFilterKey();           // 'StatusFilter'
    $errors = $e->getErrors();           // ['value' => ['Invalid value']]
    $messageBag = $e->getMessageBag();
    $firstErrors = $e->getFirstErrors(); // ['value' => 'Invalid value']
}
```

---

### 12. FilterDefinition

Metadata about a filter (used internally and for UI):

```php
$definition = $filter->toDefinition();

// Access metadata
$definition->getKey(): string                      // 'StatusFilter'
$definition->getType(): FilterTypeEnum             // FilterTypeEnum::SELECT
$definition->getColumn(): string                   // 'status'
$definition->getLabel(): string                    // 'Status'
$definition->getAllowedMatchModes(): array         // [IsMatchMode, IsNotMatchMode, ...]
$definition->getDefaultMatchMode(): MatchModeContract
$definition->isNullable(): bool
$definition->getOptions(): array                   // ['active' => 'Active', ...]
$definition->getMeta(): array                      // []
$definition->getRelation(): ?string
$definition->hasRelation(): bool

// Serialization
$definition->toArray()
json_encode($definition)
```

---

### 13. BetweenValue DTO

Type-safe value object for BETWEEN match mode:

```php
// Create
$between = new BetweenValue(min: 10, max: 100);
$between = BetweenValue::fromArray(['min' => 10, 'max' => 100]);
$between = BetweenValue::fromArray([10, 100]);  // Indexed array

// Access
$between->min   // 10
$between->max   // 100

// Convert
$between->toArray()  // ['min' => 10, 'max' => 100]

// In filter value
$fv = FilterValue::for(CountFilter::class)->between(10, 100);
$value = $fv->getValue();  // array ['min' => 10, 'max' => 100]

// After sanitization
$sanitized = $filter->sanitizeValue(['min' => 10, 'max' => 100], MatchMode::between());
// → BetweenValue(min: 10, max: 100)
```

---

## Advanced Features

### Complex AND/OR Logic

```php
// Simple AND
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);
// SQL: WHERE status = 'active' AND count > 10

// Simple OR
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');
// SQL: WHERE status = 'active' OR status = 'pending'

// Complex: (active AND count > 10) OR (pending AND count >= 100)
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10)
    ->orWhere(function ($group) {
        $group->where(StatusFilter::class)->is('pending');
        $group->where(CountFilter::class)->gte(100);
    });
// SQL: WHERE (status = 'active' AND count > 10) OR (status = 'pending' AND count >= 100)
```

### Custom Match Modes

```php
// 1. Implement MatchModeContract
class CaseInsensitiveMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'caseInsensitive';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->whereRaw("LOWER($column) = ?", [strtolower($value)]);
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        return $collection->filter(function ($item) use ($column, $value) {
            return strtolower(data_get($item, $column)) === strtolower($value);
        });
    }
}

// 2. Register the mode
MatchMode::register('caseInsensitive', CaseInsensitiveMatchMode::class);

// 3. Use in filters
class NameFilter extends TextFilter
{
    public function allowedModes(): array
    {
        return [
            new ContainsMatchMode,
            new CaseInsensitiveMatchMode,  // Custom mode
            // ...
        ];
    }
}
```

### Custom Filter Logic

```php
class CustomFilter extends SelectFilter
{
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        // Return false to use default mode logic
        if (!$mode instanceof CustomMatchMode) {
            return false;
        }

        // Custom query logic
        $query->whereRaw("JSON_EXTRACT(data, '$.key') = ?", [$value]);

        // Return true to indicate custom logic was applied
        return true;
    }
}
```

---

## Usage Examples

### Basic Query Filtering

```php
// Single filter
Koi::query()
    ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
    ->get();

// Multiple filters (AND logic)
Koi::query()
    ->applyFilters([
        FilterValue::for(KoiStatusFilter::class)->is('active'),
        FilterValue::for(KoiCountFilter::class)->gt(10),
    ])
    ->get();

// Via FilterSelection
$selection = FilterSelection::make()
    ->where(KoiStatusFilter::class)->is('active')
    ->where(KoiCountFilter::class)->gt(10);

Koi::query()->applySelection($selection)->get();
```

### Collection Filtering

```php
$collection = Koi::all();

// Single filter
Koi::filterCollectionWith(
    $collection,
    FilterValue::for(KoiStatusFilter::class)->is('active')
);

// Multiple filters
Koi::filterCollection($collection, [
    FilterValue::for(KoiStatusFilter::class)->is('active'),
    FilterValue::for(KoiCountFilter::class)->gt(10),
]);

// Via FilterSelection
$selection = FilterSelection::make()
    ->where(KoiStatusFilter::class)->is('active')
    ->where(KoiCountFilter::class)->gt(10);

Koi::filterCollectionWithSelection($collection, $selection);
```

### Relation Filtering

```php
// In Model filterResolver
protected static function filterResolver(): Closure
{
    return fn () => [
        // Kois that have a pond with water_type = 'fresh'
        PondWaterTypeFilter::via('pond'),
        
        // Kois that don't have a pond with water_type = 'fresh'
        PondWaterTypeFilter::viaDoesntHave('pond'),
        
        // Kois without any pond
        SomeFilter::withoutRelation('pond'),
    ];
}

// Usage
Koi::query()
    ->applyFilter(PondWaterTypeFilter::via('pond')->value('fresh'))
    ->get();
```

### JSON Persistence

```php
// Save filters to database
$selection = FilterSelection::make('Active Kois')
    ->description('Show active kois with count > 10')
    ->where(KoiStatusFilter::class)->is('active')
    ->where(KoiCountFilter::class)->gt(10);

// Store as JSON
$json = $selection->toJson();
DB::table('saved_filters')->insert(['json' => $json]);

// Retrieve and apply
$json = DB::table('saved_filters')->first()->json;
$selection = FilterSelection::fromJson($json);
$kois = Koi::query()->applySelection($selection)->get();
```

### Dynamic Filters

```php
// In filterResolver
protected static function filterResolver(): Closure
{
    return fn () => [
        SelectFilter::dynamic('status')
            ->withColumn('status')
            ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']),
        
        IntegerFilter::dynamic('count')
            ->withColumn('item_count'),
        
        TextFilter::dynamic('name')
            ->withColumn('full_name')
            ->withNullable(true),
    ];
}

// Usage (same as static filters)
$result = Koi::query()
    ->applyFilter(FilterValue::for('status')->is('active'))
    ->get();
```

---

## Testing

**Test Coverage**: 5,278 lines of test code

**Test Files**:
- `TutorialTest.php` - Complete workflow tutorial with 13 concepts
- `MatchModesTest.php` - All 17 match modes demonstrated
- `BestPracticesTest.php` - Advanced usage patterns
- `FilterableTest.php` - Trait integration
- `FilterGroupTest.php` - AND/OR logic
- `FilterSelectionTest.php` - Selection functionality
- `QueryApplicatorTest.php` - Query application
- `CollectionApplicatorTest.php` - Collection filtering
- `DynamicFilterTest.php` - Runtime filter creation
- `FilterClassTest.php` - Filter base class
- `ArchTest.php` - Architecture rules

---

## Implementation Status

### Completed Features ✅

- Filter Types: SELECT, INTEGER, TEXT, BOOLEAN
- 17 Match Modes with full documentation
- QueryApplicator with Eloquent integration
- CollectionApplicator for in-memory filtering
- FilterSelection with AND/OR logic
- Nested FilterGroups for complex conditions
- Dynamic Filters (create at runtime)
- Filterable Model Trait
- Relation Filtering (via, viaDoesntHave, withoutRelation)
- JSON Serialization/Deserialization
- Value Sanitization & Validation
- Type-Safe Values with typedValue()
- Custom Filter Logic (apply() override)
- Custom Match Modes (MatchModeContract)
- BetweenValue DTO for range queries
- 263+ unit tests

### Planned Features

- Additional Filter Types: DATE, DATETIME, DECIMAL
- MULTI_SELECT type (JSON array support)
- UI Packages (separate repositories):
  - filter-blade (Blade template components)
  - filter-livewire (Livewire components)
  - filter-filament (Filament integration)

---

## Key Files to Read First

1. **`src/Filters/Filter.php`** - Base class for all filters
2. **`src/Data/FilterValue.php`** & **`FilterValueBuilder.php`** - Creating filter conditions
3. **`src/Selections/FilterSelection.php`** & **`FilterGroup.php`** - Grouping filters with AND/OR
4. **`src/Query/QueryApplicator.php`** - Applying filters to queries
5. **`src/Collection/CollectionApplicator.php`** - Applying filters to collections
6. **`src/Concerns/Filterable.php`** - Model trait integration
7. **`tests/Tutorial/TutorialTest.php`** - Real-world usage patterns

---

## Summary Table

| Concept | Classes | Key Methods | Usage |
|---------|---------|-------------|-------|
| Filter Types | SelectFilter, IntegerFilter, TextFilter, BooleanFilter | column(), type(), allowedModes() | Extend for custom filters |
| Match Modes | 17 implementations | key(), apply(), applyToCollection() | Apply conditions to data |
| FilterValue | FilterValue, FilterValueBuilder | for(), is(), gt(), contains() | Create single filter condition |
| Selection | FilterSelection, FilterGroup | where(), orWhere(), addGroup() | Group multiple filters |
| Application | QueryApplicator, CollectionApplicator | withFilters(), applyFilter(), applySelection() | Apply filters to data |
| Integration | Filterable trait | applyFilter(), applyFilters(), applySelection() | Add to models |
| Dynamic | DynamicSelectFilter, DynamicTextFilter, etc. | dynamic(), with*() | Create filters at runtime |
| Validation | FilterValidationException | validationRules(), sanitizeValue(), typedValue() | Ensure data quality |

