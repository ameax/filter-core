# Match Modes

Filter-core provides 18 built-in match modes for different comparison operations.

## Overview

| Mode | Key | Description | Example |
|------|-----|-------------|---------|
| `IsMatchMode` | `is` | Exact equality | `= value` |
| `IsNotMatchMode` | `is_not` | Not equal | `!= value` |
| `AnyMatchMode` | `any` | One of values | `IN (a, b, c)` |
| `AllMatchMode` | `all` | All values match | Multiple conditions |
| `NoneMatchMode` | `none` | None of values | `NOT IN (a, b, c)` |
| `GreaterThanMatchMode` | `gt` | Greater than | `> value` |
| `GreaterThanOrEqualMatchMode` | `gte` | Greater or equal | `>= value` |
| `LessThanMatchMode` | `lt` | Less than | `< value` |
| `LessThanOrEqualMatchMode` | `lte` | Less or equal | `<= value` |
| `BetweenMatchMode` | `between` | Range (inclusive) | `BETWEEN a AND b` |
| `ContainsMatchMode` | `contains` | Text contains | `LIKE %value%` |
| `StartsWithMatchMode` | `starts_with` | Starts with | `LIKE value%` |
| `EndsWithMatchMode` | `ends_with` | Ends with | `LIKE %value` |
| `RegexMatchMode` | `regex` | Regular expression | `REGEXP pattern` |
| `EmptyMatchMode` | `empty` | Is null/empty | `IS NULL` |
| `NotEmptyMatchMode` | `not_empty` | Is not null/empty | `IS NOT NULL` |
| `DateRangeMatchMode` | `date_range` | Within date range | `BETWEEN start AND end` |
| `NotInDateRangeMatchMode` | `not_in_date_range` | Outside date range | `NOT BETWEEN start AND end` |

## Using Match Modes

### Fluent Builder (Recommended)

```php
use Ameax\FilterCore\Data\FilterValue;
use App\Filters\StatusFilter;

// Each mode has a shorthand method
FilterValue::for(StatusFilter::class)->is('active');
FilterValue::for(StatusFilter::class)->isNot('deleted');
FilterValue::for(StatusFilter::class)->any(['a', 'b']);
FilterValue::for(CountFilter::class)->gt(10);
FilterValue::for(CountFilter::class)->between(10, 100);
FilterValue::for(NameFilter::class)->contains('john');
```

### Direct Instantiation

```php
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;

// Create mode instances directly
$filterValue = new FilterValue('StatusFilter', new IsMatchMode(), 'active');
$filterValue = new FilterValue('CountFilter', new GreaterThanMatchMode(), 10);
```

### Factory Methods

```php
use Ameax\FilterCore\MatchModes\MatchMode;

// Static factory methods
$mode = MatchMode::is();       // IsMatchMode
$mode = MatchMode::gt();       // GreaterThanMatchMode
$mode = MatchMode::between();  // BetweenMatchMode
$mode = MatchMode::contains(); // ContainsMatchMode

// Get by key string
$mode = MatchMode::get('is');      // IsMatchMode
$mode = MatchMode::get('gt');      // GreaterThanMatchMode
$mode = MatchMode::get('between'); // BetweenMatchMode
```

## Equality Modes

### `is` - Exact Match

```php
FilterValue::for(StatusFilter::class)->is('active');
// SQL: WHERE status = 'active'
```

### `isNot` - Not Equal

```php
FilterValue::for(StatusFilter::class)->isNot('deleted');
// SQL: WHERE status != 'deleted'
```

## Set Modes

### `any` - One of Values (IN)

```php
FilterValue::for(StatusFilter::class)->any(['active', 'pending']);
// SQL: WHERE status IN ('active', 'pending')
```

### `none` - None of Values (NOT IN)

```php
FilterValue::for(StatusFilter::class)->none(['inactive', 'deleted']);
// SQL: WHERE status NOT IN ('inactive', 'deleted')
```

### `all` - All Values Match

Used for array/JSON columns where all values must be present:

```php
FilterValue::for(TagsFilter::class)->all(['php', 'laravel']);
// Checks that the column contains ALL specified values
```

## Comparison Modes

### `gt` - Greater Than

```php
FilterValue::for(CountFilter::class)->gt(10);
// SQL: WHERE count > 10
```

### `gte` - Greater Than or Equal

```php
FilterValue::for(CountFilter::class)->gte(10);
// SQL: WHERE count >= 10
```

### `lt` - Less Than

```php
FilterValue::for(CountFilter::class)->lt(100);
// SQL: WHERE count < 100
```

### `lte` - Less Than or Equal

```php
FilterValue::for(CountFilter::class)->lte(100);
// SQL: WHERE count <= 100
```

### `between` - Range (Inclusive)

```php
FilterValue::for(CountFilter::class)->between(10, 100);
// SQL: WHERE count BETWEEN 10 AND 100
```

The `between` mode accepts either two arguments or a `BetweenValue`:

```php
use Ameax\FilterCore\Data\BetweenValue;

// Two arguments
FilterValue::for(CountFilter::class)->between(10, 100);

// BetweenValue object
$range = new BetweenValue(10, 100);
FilterValue::for(CountFilter::class)->mode(MatchMode::between())->value($range);
```

## Text Modes

### `contains` - Partial Match

```php
FilterValue::for(NameFilter::class)->contains('john');
// SQL: WHERE name LIKE '%john%'
```

### `startsWith` - Prefix Match

```php
FilterValue::for(NameFilter::class)->startsWith('John');
// SQL: WHERE name LIKE 'John%'
```

### `endsWith` - Suffix Match

```php
FilterValue::for(NameFilter::class)->endsWith('Doe');
// SQL: WHERE name LIKE '%Doe'
```

### `regex` - Regular Expression

```php
FilterValue::for(NameFilter::class)->regex('^J.*n$');
// SQL: WHERE name REGEXP '^J.*n$'
```

**Note**: Regex syntax depends on your database. MySQL uses REGEXP, PostgreSQL uses ~.

## Null Modes

### `empty` - Is Null/Empty

```php
FilterValue::for(DeletedAtFilter::class)->empty();
// SQL: WHERE deleted_at IS NULL
```

### `notEmpty` - Is Not Null/Empty

```php
FilterValue::for(DeletedAtFilter::class)->notEmpty();
// SQL: WHERE deleted_at IS NOT NULL
```

**Note**: To use `empty`/`notEmpty`, the filter must have `nullable()` return `true`:

```php
class DeletedAtFilter extends TextFilter
{
    public function column(): string
    {
        return 'deleted_at';
    }

    public function nullable(): bool
    {
        return true;
    }
}
```

## Mode Availability by Filter Type

Not all modes work with all filter types:

| Filter Type | Available Modes |
|-------------|-----------------|
| `SelectFilter` | `is`, `isNot`, `any`, `all`, `none`, `empty`*, `notEmpty`* |
| `IntegerFilter` | `is`, `isNot`, `gt`, `gte`, `lt`, `lte`, `between`, `empty`*, `notEmpty`* |
| `DecimalFilter` | `is`, `isNot`, `any`, `none`, `gt`, `gte`, `lt`, `lte`, `between`, `empty`*, `notEmpty`* |
| `TextFilter` | `is`, `isNot`, `contains`, `startsWith`, `endsWith`, `regex`, `empty`*, `notEmpty`* |
| `BooleanFilter` | `is`, `empty`*, `notEmpty`* |
| `DateFilter` | `dateRange`, `notInDateRange`, `empty`*, `notEmpty`* |

\* Only when `nullable()` returns `true`

## MatchModeContract Interface

All match modes implement `MatchModeContract`:

```php
interface MatchModeContract
{
    /**
     * Unique identifier for this mode (e.g., 'is', 'gt', 'contains')
     */
    public function key(): string;

    /**
     * Human-readable label
     */
    public function label(): string;

    /**
     * Apply this mode to a query
     */
    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void;

    /**
     * Apply this mode to a collection item
     */
    public function matchesCollection(mixed $itemValue, mixed $filterValue): bool;
}
```

## Custom Match Modes

You can create custom match modes by implementing `MatchModeContract`:

```php
<?php

namespace App\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CaseInsensitiveMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'ilike';
    }

    public function label(): string
    {
        return 'Case Insensitive Like';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->whereRaw("LOWER({$column}) LIKE ?", [strtolower("%{$value}%")]);
    }

    public function matchesCollection(mixed $itemValue, mixed $filterValue): bool
    {
        return str_contains(
            strtolower((string) $itemValue),
            strtolower((string) $filterValue)
        );
    }
}
```

Register your custom mode:

```php
use Ameax\FilterCore\MatchModes\MatchMode;

// Register once (e.g., in a service provider)
MatchMode::register('ilike', CaseInsensitiveMatchMode::class);

// Now accessible via factory
$mode = MatchMode::get('ilike');
```

## Next Steps

- [Filter Selections](./04-filter-selections.md) - Combine filters with AND/OR logic
- [Advanced Usage](./09-advanced-usage.md) - Custom filter logic and extensibility
