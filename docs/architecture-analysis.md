# Architecture Analysis & Known Limitations

This document provides a critical analysis of the filter-core package design, identifying architectural weaknesses, limitations, and potential improvements.

## Resolved Issues

The following issues from this analysis have been addressed:

| Issue | Status | Solution |
|-------|--------|----------|
| **1.3 Relation Not in FilterDefinition** | RESOLVED | `FilterDefinition::$relation` property added |
| **2.1 Closed Match Mode System** | RESOLVED | `MatchModeContract` interface + class-based MatchModes |
| **2.2 No Custom Filter Logic** | RESOLVED | `Filter::apply()` method allows custom query logic |
| **2.4 No Filter Transformation** | RESOLVED | `Filter::sanitizeValue()` for input transformation |
| **5.1 No Value Type Validation** | RESOLVED | `Filter::validationRules()` with Laravel Validator |
| **5.2 No Options Validation** | RESOLVED | `SelectFilter` validates against defined options |
| **Type Safety** | RESOLVED | `Filter::typedValue()` with strict PHP types |
| **Range Values** | RESOLVED | `BetweenValue` DTO for type-safe min/max |

## Table of Contents

1. [Architecture Weaknesses](#1-architecture-weaknesses)
2. [Extensibility Problems](#2-extensibility-problems)
3. [Flexibility Deficits](#3-flexibility-deficits)
4. [Serialization Issues](#4-serialization-issues)
5. [Validation Gaps](#5-validation-gaps)
6. [Performance Considerations](#6-performance-considerations)
7. [Developer Experience Issues](#7-developer-experience-issues)
8. [Recommended Improvements](#8-recommended-improvements)

---

## 1. Architecture Weaknesses

### 1.1 Duplicate Match Mode Responsibility

Match mode definitions exist in multiple places:

```php
// Location 1: Enum
FilterTypeEnum::defaultMatchModes()

// Location 2: FilterDefinition
FilterDefinition::getDefaultMatchModesForType()

// Location 3: Filter classes
SelectFilter::allowedModes()
```

**Problem:** Same logic in 3 places. Changes require synchronization across all.

**Impact:** Medium - Maintenance burden, potential for inconsistency.

**Recommendation:** Single Source of Truth - only `FilterTypeEnum` should define match modes.

---

### 1.2 Filter Key Inconsistency

```php
// Static filter: Key = class basename
KoiStatusFilter::key() // → "KoiStatusFilter"

// Dynamic filter: Key = arbitrary string
SelectFilter::dynamic('my_key')->getKey() // → "my_key"
```

**Problem:** Different key strategies. During JSON deserialization, it's impossible to determine if a key refers to a static class or dynamic filter.

**Impact:** High - `fromJson()` cannot reconstruct the original filter type.

**Example Issue:**
```php
// Saved JSON
{"filters": [{"filter": "status", "mode": "is", "value": "active"}]}

// When loading: Is "status" a class KoiStatusFilter or dynamic key "status"?
// Currently impossible to determine
```

---

### 1.3 Relation Not in FilterDefinition ✅ RESOLVED

**Original Problem:** `FilterDefinition` did not store relation information, causing relation filters to fail in legacy mode.

**Solution Implemented:**

```php
// FilterDefinition now has relation property
new FilterDefinition(
    key: 'PondWaterTypeFilter',
    type: FilterTypeEnum::SELECT,
    column: 'water_type',
    relation: 'pond',  // ← NEW
);

// Filter::toDefinition() now includes relation
$filter = PondWaterTypeFilter::via('pond');
$definition = $filter->toDefinition();
$definition->getRelation();  // → "pond"
$definition->hasRelation();  // → true

// toArray() includes relation for serialization
$definition->toArray();
// → ['key' => '...', 'column' => '...', 'relation' => 'pond', ...]
```

---

### 1.4 Circular Dependency Risk

```php
// Filter.php imports DynamicFilter interface
use Ameax\FilterCore\Filters\Dynamic\DynamicFilter;

// DynamicSelectFilter extends Filter
class DynamicSelectFilter extends Filter implements DynamicFilter
```

**Problem:** Base class knows about its specialized implementations.

**Impact:** Low - Works but violates dependency inversion principle.

---

## 2. Extensibility Problems

### 2.1 Closed Match Mode System ✅ RESOLVED

**Original Problem:** Match modes were defined as an enum, making it impossible to add custom modes without modifying the core package.

**Solution Implemented:** Class-based MatchMode system with `MatchModeContract` interface.

```php
// Each MatchMode is now a class implementing MatchModeContract
interface MatchModeContract {
    public function key(): string;
    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void;
}

// Built-in modes: IsMatchMode, IsNotMatchMode, ContainsMatchMode,
// AnyMatchMode, NoneMatchMode, GreaterThanMatchMode, LessThanMatchMode,
// BetweenMatchMode, EmptyMatchMode, NotEmptyMatchMode

// Custom MatchMode example:
class StartsWithMatchMode implements MatchModeContract {
    public function key(): string { return 'starts_with'; }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void {
        $query->where($column, 'LIKE', $value . '%');
    }
}

// Register custom modes:
MatchMode::register('startsWith', StartsWithMatchMode::class);

// Use via magic method:
MatchMode::startsWith(); // Returns StartsWithMatchMode instance
```

**Two-Level Override System:**
1. **MatchMode-Level:** Each MatchMode class contains its own `apply()` logic
2. **Filter-Level:** Filters can override via `Filter::apply()` returning `true`

```php
// In QueryApplicator:
$customApplied = $filter->apply($query, $matchMode, $value);
if (!$customApplied) {
    $matchMode->apply($query, $column, $value);
}
```

---

### 2.2 No Custom Filter Logic

```php
// Current: Filter only returns column name
abstract public function column(): string;

// What if I need:
// - Search across multiple columns
// - Case-insensitive search
// - JSON column queries
// - Full-text search
// - Computed values (age from birthday)
```

**Problem:** Filters cannot define custom query logic.

**Impact:** High - Many real-world scenarios unsupported.

**Recommendation:** Allow filters to override query application:

```php
abstract class Filter {
    /**
     * Apply this filter to the query.
     * Override for custom logic.
     */
    public function apply(Builder $query, MatchModeEnum $mode, mixed $value): void
    {
        // Default: delegate to QueryApplicator
        // Override for custom behavior
    }

    /**
     * Whether this filter handles its own query logic.
     */
    public function hasCustomApply(): bool
    {
        return false;
    }
}

// Example custom filter:
class FullNameFilter extends TextFilter {
    public function apply(Builder $query, MatchModeEnum $mode, mixed $value): void
    {
        $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$value}%");
    }

    public function hasCustomApply(): bool
    {
        return true;
    }
}
```

---

### 2.3 No Support for Computed/Virtual Columns

```php
// How to filter by "age" when only "birthday" is in database?
// How to filter by "full_name" (first_name + last_name)?
// How to filter by aggregates (order count)?
```

**Currently impossible** without custom filter logic (see 2.2).

---

### 2.4 No Filter Transformation/Preprocessing

```php
// What if I need to:
// - Normalize input (trim, lowercase)
// - Convert date formats
// - Resolve lookups (name → id)
```

**Recommendation:** Add transformation hook:

```php
abstract class Filter {
    public function transformValue(mixed $value, MatchModeEnum $mode): mixed
    {
        return $value; // Override for custom transformation
    }
}
```

---

## 3. Flexibility Deficits

### 3.1 Only AND Logic

```php
FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->greaterThan(5);

// Generates: status = 'active' AND count > 5
```

**Problem:** No OR groups, no nested conditions.

**Impact:** High - Many filter UIs require OR logic.

**Missing functionality:**
```php
// Desired:
$selection->or(function($group) {
    $group->where(StatusFilter::class)->is('active');
    $group->where(StatusFilter::class)->is('pending');
});

// Or:
$selection->whereAny([
    StatusFilter::value()->is('active'),
    StatusFilter::value()->is('pending'),
]);
```

**Note:** `GroupOperatorEnum` exists but is not implemented.

---

### 3.2 No Negation at Selection Level

```php
// How to express: NOT (status = 'active' AND count > 5)?
```

**Currently impossible** with existing design.

---

### 3.3 Limited Relation Support

```php
// Current: Only simple whereHas
whereHas('pond', fn($q) => $q->where('water_type', 'fresh'))

// Not supported:
// - whereDoesntHave (Kois WITHOUT a pond)
// - withCount + having (Kois with at least 3 tags)
// - Nested relations (pond.location.country)
// - morphTo relations
// - Aggregate filters (SUM, AVG on relations)
```

**Impact:** Medium - Common use cases unsupported.

---

### 3.4 No Filter Dependencies/Visibility

```php
// What if CountryFilter should only appear when RegionFilter is set?
// What if some filters are mutually exclusive?
```

**Recommendation:** Add dependency system:

```php
abstract class Filter {
    public function dependsOn(): ?string
    {
        return null; // Return filter key this depends on
    }

    public function isVisibleWhen(FilterSelection $selection): bool
    {
        return true;
    }
}
```

---

## 4. Serialization Issues

### 4.1 FilterSelection Loses Filter Type Information

```php
$selection->toJson();
// → {"filters": [{"filter": "KoiStatusFilter", "mode": "is", "value": "active"}]}
```

**Problem:** When loading, we don't know:
- Is `KoiStatusFilter` a class or dynamic key?
- What `allowedModes` does the filter have?
- What `options` does a select filter have?

**Impact:** High - API cannot validate if mode is allowed.

**Recommendation:** Include filter metadata or use a registry:

```php
// Option A: Include metadata
{
    "filters": [{
        "filter": "KoiStatusFilter",
        "mode": "is",
        "value": "active",
        "_meta": {
            "type": "static",
            "class": "App\\Filters\\KoiStatusFilter"
        }
    }]
}

// Option B: Filter Registry
FilterRegistry::register('KoiStatusFilter', KoiStatusFilter::class);
FilterRegistry::resolve('KoiStatusFilter'); // → KoiStatusFilter instance
```

---

### 4.2 Dynamic Filters Not Fully Serializable

```php
$dynamic = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withOptions(['a' => 'A', 'b' => 'B']);

// How to save and restore this completely?
$dynamic->toDefinition()->toArray();
// Options included, but not reconstructable to DynamicSelectFilter
```

**Recommendation:** Add `toArray()` / `fromArray()` to dynamic filters:

```php
$array = $dynamic->toArray();
// → ['key' => 'status', 'type' => 'select', 'column' => 'status', 'options' => [...]]

DynamicSelectFilter::fromArray($array);
// → Fully reconstructed filter
```

---

### 4.3 No Versioning for Serialized Data

```php
// What happens when filter schema changes?
// Old saved selections may become invalid
```

**Recommendation:** Add schema version:

```php
{
    "version": "1.0",
    "filters": [...]
}
```

---

## 5. Validation Gaps

### 5.1 No Value Type Validation

```php
// Boolean filter accepts any value
BooleanFilter::value()->is('banana') // No error!

// Integer filter accepts strings
IntegerFilter::value()->greaterThan('not a number') // Runtime error at DB level
```

**Impact:** High - Invalid data reaches database.

**Recommendation:** Add validation:

```php
abstract class Filter {
    abstract public function validateValue(mixed $value, MatchModeEnum $mode): bool;

    public function getValidationRules(MatchModeEnum $mode): array
    {
        return []; // Laravel validation rules
    }
}

class IntegerFilter extends Filter {
    public function validateValue(mixed $value, MatchModeEnum $mode): bool
    {
        if ($mode === MatchModeEnum::BETWEEN) {
            return is_array($value)
                && isset($value['min'], $value['max'])
                && is_numeric($value['min'])
                && is_numeric($value['max']);
        }
        return is_numeric($value);
    }
}
```

---

### 5.2 No Options Validation for Select Filters

```php
$filter = SelectFilter::dynamic('status')
    ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']);

// But this is allowed:
new FilterValue('status', MatchModeEnum::IS, 'invalid_option') // No error!
```

**Recommendation:** Validate against options:

```php
class SelectFilter extends Filter {
    public function validateValue(mixed $value, MatchModeEnum $mode): bool
    {
        $options = $this->options();
        if (empty($options)) {
            return true; // No options defined, allow anything
        }

        $values = is_array($value) ? $value : [$value];
        foreach ($values as $v) {
            if (!array_key_exists($v, $options)) {
                return false;
            }
        }
        return true;
    }
}
```

---

### 5.3 No Match Mode Compatibility Check at Build Time

```php
// TextFilter doesn't support BETWEEN, but:
TextFilter::value()->between(1, 10) // Compiles fine, fails at runtime
```

**Recommendation:** Check at builder level:

```php
class FilterValueBuilder {
    public function between($min, $max): FilterValue|FilterSelection
    {
        if (!in_array(MatchModeEnum::BETWEEN, $this->getAllowedModes())) {
            throw new InvalidArgumentException(
                "BETWEEN mode not allowed for filter {$this->filterClass}"
            );
        }
        return $this->mode(MatchModeEnum::BETWEEN)->value(['min' => $min, 'max' => $max]);
    }
}
```

---

## 6. Performance Considerations

### 6.1 N+1 with Multiple Relation Filters

```php
Koi::query()->applyFilters([
    FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),
    FilterValue::for(PondCapacityFilter::class)->greaterThan(1000),
]);

// Generates:
// WHERE EXISTS (SELECT * FROM ponds WHERE kois.pond_id = ponds.id AND water_type = 'fresh')
// AND EXISTS (SELECT * FROM ponds WHERE kois.pond_id = ponds.id AND capacity > 1000)

// Could be optimized to:
// WHERE EXISTS (SELECT * FROM ponds WHERE kois.pond_id = ponds.id AND water_type = 'fresh' AND capacity > 1000)
```

**Impact:** Medium - Performance degradation with many relation filters.

**Recommendation:** Combine relation filters:

```php
class QueryApplicator {
    protected function applyRelationFilters(array $filtersByRelation): void
    {
        foreach ($filtersByRelation as $relation => $filters) {
            $this->query->whereHas($relation, function($query) use ($filters) {
                foreach ($filters as $filter) {
                    // Apply all filters for this relation in one whereHas
                }
            });
        }
    }
}
```

---

### 6.2 Filter Instantiation on Every Request

```php
// Filterable trait caches per class
static::$resolvedFilters[static::class]

// But: toDefinition() called every time in withFilters()
public function withFilters(array $filters): self
{
    foreach ($filters as $filter) {
        $filterInstance = is_string($filter) ? $filter::make() : $filter;
        $this->filterDefinitions[$key] = $filterInstance->toDefinition(); // Called every time
    }
}
```

**Impact:** Low - Minor overhead.

**Recommendation:** Cache definitions alongside filter instances.

---

### 6.3 No Query Optimization Hints

```php
// No way to add indexes hints
// No way to force specific query plans
// No way to add query comments for debugging
```

---

## 7. Developer Experience Issues

### 7.1 Inconsistent Naming Convention

```php
// Filter class (getters without prefix)
$filter->nullable()      // Returns bool
$filter->column()        // Returns string

// Dynamic filter (setters with 'with' prefix)
$filter->withNullable()  // Setter
$filter->withColumn()    // Setter

// Inconsistent patterns:
// - Some getters: column(), label(), type()
// - Some getters: getRelation(), getKey()
// - Setters: withColumn(), withLabel()
```

**Recommendation:** Consistent naming:

```php
// Option A: Laravel-style (property name = getter, with prefix = setter)
$filter->column();           // Get
$filter->column('status');   // Set (overloaded)

// Option B: Explicit prefixes
$filter->getColumn();
$filter->setColumn('status');

// Option C: Current + consistent getters
$filter->column();           // Get (no get prefix for simple props)
$filter->getRelation();      // Get (with prefix for complex props)
$filter->withColumn();       // Set
```

---

### 7.2 No IDE Support for Dynamic Filters in Selection

```php
$selection->where(KoiStatusFilter::class)->is('active') // ✓ IDE knows is()

// But for dynamic filter keys:
$selection->where('dynamic_key')-> // ✗ No autocomplete possible
```

**Impact:** Low - Inherent limitation of dynamic approach.

---

### 7.3 Missing Debugging Tools

```php
// No way to:
// - Dump generated SQL for a selection
// - Trace which filters were applied
// - Get explanation of filter logic
```

**Recommendation:** Add debugging helpers:

```php
$selection->toSql(Koi::query()); // Returns SQL string
$selection->explain();           // Human-readable explanation
QueryApplicator::for($query)->withFilters([...])->dd(); // Dump and die
```

---

### 7.4 No Filter Discovery/Listing

```php
// How to get all available filters for a model?
Koi::getFilters(); // Returns instances, but not their metadata

// Missing:
Koi::getFilterDefinitions(); // All definitions with options, modes, etc.
Koi::getFilterByKey('KoiStatusFilter'); // Get specific filter
```

---

## 8. Recommended Improvements

### Priority Matrix

| Priority | Issue | Solution | Effort |
|----------|-------|----------|--------|
| **Critical** | No custom filter logic | Add `Filter::apply()` method | Medium |
| **Critical** | No value validation | Add `Filter::validateValue()` | Medium |
| **High** | Only AND logic | Implement `FilterGroup` with operators | High |
| **High** | Match modes not extensible | Strategy pattern / handler registry | High |
| **High** | Serialization loses context | Filter registry + metadata | Medium |
| **Medium** | Relation not in definition | Add `FilterDefinition::$relation` | Low |
| **Medium** | N+1 relation queries | Combine relation filters | Medium |
| **Medium** | No options validation | Validate in SelectFilter | Low |
| **Low** | Inconsistent naming | Refactoring | Low |
| **Low** | Missing debugging tools | Add helper methods | Low |

### Suggested Roadmap

#### Phase 1: Critical Fixes
1. Add `Filter::apply()` for custom query logic
2. Add value validation system
3. Add filter registry for serialization

#### Phase 2: Flexibility
1. Implement `FilterGroup` with AND/OR operators
2. Add match mode handler registry
3. Support `whereDoesntHave` for relations

#### Phase 3: Polish
1. Consistent naming refactoring
2. Add debugging tools
3. Performance optimizations
4. Comprehensive documentation

---

## Conclusion

The filter-core package provides a solid foundation for basic filtering needs. The fluent API and separation of concerns are well-designed. However, for production use in complex applications, the following limitations should be addressed:

1. **Custom filter logic** - Essential for real-world scenarios
2. **Value validation** - Critical for data integrity
3. **OR logic support** - Required for advanced filter UIs
4. **Extensible match modes** - Needed for domain-specific operators

The package is suitable for simple CRUD applications but may require significant extension for complex filtering requirements.
