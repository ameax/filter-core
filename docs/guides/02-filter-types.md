# Filter Types

Filter-core provides 6 base filter types, each optimized for different data types.

## Overview

| Filter Type | Purpose | Allowed Match Modes |
|-------------|---------|---------------------|
| `SelectFilter` | Predefined options | `is`, `isNot`, `any`, `all`, `none` |
| `IntegerFilter` | Whole number comparisons | `is`, `isNot`, `gt`, `gte`, `lt`, `lte`, `between` |
| `DecimalFilter` | Decimal/float comparisons | `is`, `isNot`, `any`, `none`, `gt`, `gte`, `lt`, `lte`, `between` |
| `TextFilter` | Text searching | `is`, `isNot`, `contains`, `startsWith`, `endsWith`, `regex` |
| `BooleanFilter` | True/False values | `is` |
| `DateFilter` | Date/datetime ranges | `dateRange`, `notInDateRange` |

## SelectFilter

Use for fields with predefined options (status, category, type).

### Definition

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\SelectFilter;

class StatusFilter extends SelectFilter
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

    // Optional: Custom label for UI
    public function label(): string
    {
        return 'User Status';
    }
}
```

### Usage

```php
use App\Filters\StatusFilter;
use Ameax\FilterCore\Data\FilterValue;

// Exact match
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->is('active'))
    ->get();

// Not equal
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->isNot('deleted'))
    ->get();

// Any of multiple values (IN)
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->any(['active', 'pending']))
    ->get();

// None of values (NOT IN)
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->none(['inactive', 'deleted']))
    ->get();
```

### Generated SQL

| Method | SQL |
|--------|-----|
| `->is('active')` | `WHERE status = 'active'` |
| `->isNot('active')` | `WHERE status != 'active'` |
| `->any(['a', 'b'])` | `WHERE status IN ('a', 'b')` |
| `->none(['a', 'b'])` | `WHERE status NOT IN ('a', 'b')` |

## IntegerFilter

Use for numeric fields (count, age, price, quantity).

### Definition

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\IntegerFilter;

class CountFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'count';
    }
}
```

### Usage

```php
use App\Filters\CountFilter;
use Ameax\FilterCore\Data\FilterValue;

// Exact match
$products = Product::query()
    ->applyFilter(FilterValue::for(CountFilter::class)->is(10))
    ->get();

// Greater than
$products = Product::query()
    ->applyFilter(FilterValue::for(CountFilter::class)->gt(100))
    ->get();

// Greater than or equal
$products = Product::query()
    ->applyFilter(FilterValue::for(CountFilter::class)->gte(100))
    ->get();

// Less than
$products = Product::query()
    ->applyFilter(FilterValue::for(CountFilter::class)->lt(50))
    ->get();

// Less than or equal
$products = Product::query()
    ->applyFilter(FilterValue::for(CountFilter::class)->lte(50))
    ->get();

// Between (inclusive)
$products = Product::query()
    ->applyFilter(FilterValue::for(CountFilter::class)->between(10, 100))
    ->get();
```

### Generated SQL

| Method | SQL |
|--------|-----|
| `->is(10)` | `WHERE count = 10` |
| `->isNot(10)` | `WHERE count != 10` |
| `->gt(100)` | `WHERE count > 100` |
| `->gte(100)` | `WHERE count >= 100` |
| `->lt(50)` | `WHERE count < 50` |
| `->lte(50)` | `WHERE count <= 50` |
| `->between(10, 100)` | `WHERE count BETWEEN 10 AND 100` |

## DecimalFilter

Use for decimal/float fields (price, weight, rating, percentage).

### Definition

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\DecimalFilter;

class PriceFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'price';
    }

    // Number of decimal places (default: 2)
    public function precision(): int
    {
        return 2;
    }

    // Optional: minimum value
    public function min(): ?float
    {
        return 0.0;
    }

    // Optional: maximum value
    public function max(): ?float
    {
        return 999999.99;
    }
}
```

### Usage

```php
use App\Filters\PriceFilter;
use Ameax\FilterCore\Data\FilterValue;

// Exact match
$products = Product::query()
    ->applyFilter(FilterValue::for(PriceFilter::class)->is(19.99))
    ->get();

// Greater than
$products = Product::query()
    ->applyFilter(FilterValue::for(PriceFilter::class)->gt(50.00))
    ->get();

// Between (inclusive)
$products = Product::query()
    ->applyFilter(FilterValue::for(PriceFilter::class)->between(10.00, 100.00))
    ->get();

// Any of multiple values
$products = Product::query()
    ->applyFilter(FilterValue::for(PriceFilter::class)->any([9.99, 19.99, 29.99]))
    ->get();
```

### Generated SQL

| Method | SQL |
|--------|-----|
| `->is(19.99)` | `WHERE price = 19.99` |
| `->isNot(19.99)` | `WHERE price != 19.99` |
| `->gt(50.00)` | `WHERE price > 50.00` |
| `->gte(50.00)` | `WHERE price >= 50.00` |
| `->lt(10.00)` | `WHERE price < 10.00` |
| `->lte(10.00)` | `WHERE price <= 10.00` |
| `->between(10, 100)` | `WHERE price BETWEEN 10 AND 100` |
| `->any([9.99, 19.99])` | `WHERE price IN (9.99, 19.99)` |

### Stored as Integer (Cents Pattern)

Some applications store decimal values as integers (e.g., price in cents: $19.99 = 1999).
DecimalFilter supports this via `storedAsInteger()`:

```php
class PriceCentsFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'price_cents'; // DB stores 1999 for $19.99
    }

    public function precision(): int
    {
        return 2;
    }

    public function storedAsInteger(): bool
    {
        return true; // Enables automatic conversion
    }
}

// User enters decimal, filter converts to integer for query
$products = Product::query()
    ->applyFilter(FilterValue::for(PriceCentsFilter::class)->is(19.99))
    ->get();
// SQL: WHERE price_cents = 1999

$products = Product::query()
    ->applyFilter(FilterValue::for(PriceCentsFilter::class)->between(10.00, 50.00))
    ->get();
// SQL: WHERE price_cents BETWEEN 1000 AND 5000
```

### Precision and Rounding

Values are automatically rounded to the specified precision:

```php
$filter = new PriceFilter(); // precision = 2

$filter->sanitizeValue(19.994, $mode);  // Returns 19.99
$filter->sanitizeValue(19.995, $mode);  // Returns 20.00
$filter->sanitizeValue('19.99', $mode); // Returns 19.99 (string converted)
```

### BetweenValue DTO

For type-safe range values, use `BetweenValue`:

```php
use Ameax\FilterCore\Data\BetweenValue;

// Create directly
$range = new BetweenValue(min: 10, max: 100);

// Create from array
$range = BetweenValue::fromArray(['min' => 10, 'max' => 100]);
$range = BetweenValue::fromArray([10, 100]); // Indexed array

// Access values
echo $range->min; // 10
echo $range->max; // 100

// Convert to array
$array = $range->toArray(); // ['min' => 10, 'max' => 100]
```

## TextFilter

Use for text search on string fields (name, description, email).

### Definition

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\TextFilter;

class NameFilter extends TextFilter
{
    public function column(): string
    {
        return 'name';
    }
}
```

### Usage

```php
use App\Filters\NameFilter;
use Ameax\FilterCore\Data\FilterValue;

// Contains (partial match)
$users = User::query()
    ->applyFilter(FilterValue::for(NameFilter::class)->contains('john'))
    ->get();

// Exact match
$users = User::query()
    ->applyFilter(FilterValue::for(NameFilter::class)->is('John Doe'))
    ->get();

// Starts with
$users = User::query()
    ->applyFilter(FilterValue::for(NameFilter::class)->startsWith('John'))
    ->get();

// Ends with
$users = User::query()
    ->applyFilter(FilterValue::for(NameFilter::class)->endsWith('Doe'))
    ->get();

// Regular expression
$users = User::query()
    ->applyFilter(FilterValue::for(NameFilter::class)->regex('^J.*n$'))
    ->get();
```

### Generated SQL

| Method | SQL |
|--------|-----|
| `->is('John')` | `WHERE name = 'John'` |
| `->contains('john')` | `WHERE name LIKE '%john%'` |
| `->startsWith('J')` | `WHERE name LIKE 'J%'` |
| `->endsWith('son')` | `WHERE name LIKE '%son'` |
| `->regex('^J.*')` | `WHERE name REGEXP '^J.*'` |

## BooleanFilter

Use for boolean fields (is_active, is_published, has_verified).

### Definition

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\BooleanFilter;

class IsActiveFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'is_active';
    }
}
```

### Usage

```php
use App\Filters\IsActiveFilter;
use Ameax\FilterCore\Data\FilterValue;

// Find active users
$users = User::query()
    ->applyFilter(FilterValue::for(IsActiveFilter::class)->is(true))
    ->get();

// Find inactive users
$users = User::query()
    ->applyFilter(FilterValue::for(IsActiveFilter::class)->is(false))
    ->get();
```

### Automatic Sanitization

BooleanFilter automatically sanitizes string inputs:

| Input | Sanitized To |
|-------|--------------|
| `'true'`, `'1'`, `'yes'`, `'on'` | `true` |
| `'false'`, `'0'`, `'no'`, `'off'` | `false` |

```php
// These all work the same way
$filter->sanitizeValue('true', MatchMode::is());  // true
$filter->sanitizeValue('1', MatchMode::is());     // true
$filter->sanitizeValue('yes', MatchMode::is());   // true
```

## Common Filter Methods

All filter types share these methods:

### Required Methods

```php
// Column name in database
public function column(): string
{
    return 'column_name';
}
```

### Optional Methods

```php
// Custom label for UI
public function label(): string
{
    return 'My Filter';
}

// Allow null values (enables empty/notEmpty modes)
public function nullable(): bool
{
    return true; // Default: false
}

// Custom allowed match modes
public function allowedModes(): array
{
    return [
        MatchMode::is(),
        MatchMode::isNot(),
    ];
}

// Default match mode
public function defaultMode(): MatchModeContract
{
    return MatchMode::is();
}
```

## Filter Key

Each filter has a unique key derived from its class name:

```php
StatusFilter::key(); // 'StatusFilter'
App\Filters\User\StatusFilter::key(); // 'StatusFilter' (namespace stripped)
```

You can reference filters by class or key:

```php
// By class (recommended - type-safe)
FilterValue::for(StatusFilter::class)->is('active');

// By key (useful for dynamic scenarios)
new FilterValue('StatusFilter', MatchMode::is(), 'active');
```

## Next Steps

- [Date Filter](./10-date-filter.md) - Date/datetime filtering with ranges
- [Match Modes](./03-match-modes.md) - All match modes in detail
- [Dynamic Filters](./07-dynamic-filters.md) - Create filters at runtime
- [Validation](./08-validation-sanitization.md) - Input validation and sanitization
