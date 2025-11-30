# TODO: Inconsistent Naming Convention

**Priority:** Low
**Status:** Completed

## Solution

Documented the existing naming conventions in `docs/guides/99-naming-conventions.md`. The current pragmatic approach (Option A) was chosen as it causes the least disruption while being documented clearly.

See the documentation for details on:
- Method naming (getters, setters, boolean methods)
- Factory methods (make, create, for, from*)
- Conversion methods (toArray, toJson, toSql, etc.)
- Class naming conventions
- Property naming conventions

---

## Original Problem

Mixed naming patterns across the codebase make the API less predictable:

```php
// Getters without prefix
$filter->nullable()      // Returns bool
$filter->column()        // Returns string

// Some getters with prefix
$filter->getRelation()
$filter->getKey()

// Setters with 'with' prefix
$filter->withNullable()
$filter->withColumn()
```

## Impact

- Minor impact on functionality
- Primarily affects developer experience and API consistency
- Makes it harder to remember method names
- Not following a clear convention

## Solution Options

### Option A: Keep Current (Pragmatic)
- Minimal BC break
- Simple properties use property name as getter: `column()`, `nullable()`
- Complex/computed properties use `get` prefix: `getRelation()`, `getKey()`
- Setters use `with` prefix: `withColumn()`
- **Pro:** Already established, minimal work
- **Con:** Not perfectly consistent

### Option B: Add get/set Prefixes Everywhere (Explicit)
```php
// All getters with prefix
$filter->getColumn();
$filter->getNullable();
$filter->getRelation();
$filter->getKey();

// All setters with prefix
$filter->setColumn('status');
$filter->setNullable(true);
// or
$filter->withColumn('status');
$filter->withNullable(true);
```
- **Pro:** Perfectly explicit
- **Con:** More verbose, BC break

### Option C: Property Syntax (Modern PHP)
```php
// Read-only properties
readonly public string $column;
readonly public bool $nullable;
readonly public ?string $relation;

// Or property accessors (PHP 8.4+)
public string $column {
    get => $this->column();
}
```
- **Pro:** Modern, clean
- **Con:** Requires PHP 8.4+ for full support, major BC break

## Recommendation

**Option A (Keep Current)** with documentation clarifying the convention:
- Simple properties: Use property name as getter (no prefix)
- Computed/complex properties: Use `get` prefix
- Immutable setters: Use `with` prefix (returns new instance or $this)

This is pragmatic and causes the least disruption.

## Documentation Task

Add a "Naming Conventions" section to contributor docs explaining the current pattern.

## Related Files

- `src/Filters/Filter.php` - Base class with mixed naming
- `src/Filters/Dynamic/` - All dynamic filters use `with*()` setters
