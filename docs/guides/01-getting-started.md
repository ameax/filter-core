# Getting Started

This guide walks you through setting up filter-core in your Laravel application.

## Installation

```bash
composer require ameax/filter-core
```

## Quick Start

### 1. Create a Filter Class

Filters are defined as classes that extend one of the base filter types. Here's a simple `SelectFilter`:

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
}
```

### 2. Add the Filterable Trait to Your Model

```php
<?php

namespace App\Models;

use Ameax\FilterCore\Concerns\Filterable;
use App\Filters\StatusFilter;
use App\Filters\CountFilter;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use Filterable;

    protected static function filterResolver(): \Closure
    {
        return fn () => [
            StatusFilter::class,
            CountFilter::class,
        ];
    }
}
```

### 3. Apply Filters to Queries

```php
use Ameax\FilterCore\Data\FilterValue;
use App\Filters\StatusFilter;

// Simple filter - find active users
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->is('active'))
    ->get();

// Multiple filters
$users = User::query()
    ->applyFilters([
        FilterValue::for(StatusFilter::class)->any(['active', 'pending']),
        FilterValue::for(CountFilter::class)->gt(10),
    ])
    ->get();
```

## Core Concepts

### FilterValue

A `FilterValue` represents a single filter condition with three components:
- **Filter Key**: Which filter to apply (e.g., `StatusFilter`)
- **Match Mode**: How to compare (e.g., `is`, `any`, `gt`)
- **Value**: The value to compare against

```php
// Verbose way
$filterValue = new FilterValue('StatusFilter', new IsMatchMode(), 'active');

// Fluent way (recommended)
$filterValue = FilterValue::for(StatusFilter::class)->is('active');

// Shortest way
$filterValue = StatusFilter::value()->is('active');
```

### Filter Types

Filter-core provides 4 base filter types:

| Type | Use Case | Example |
|------|----------|---------|
| `SelectFilter` | Predefined options | Status, Category, Type |
| `IntegerFilter` | Numeric values | Count, Age, Price |
| `TextFilter` | Text search | Name, Description |
| `BooleanFilter` | True/False | Is Active, Is Published |

### Match Modes

Each filter type supports different match modes:

| Mode | Description | Example |
|------|-------------|---------|
| `is` | Exact match | `->is('active')` |
| `isNot` | Not equal | `->isNot('deleted')` |
| `any` | One of values | `->any(['a', 'b'])` |
| `none` | None of values | `->none(['x', 'y'])` |
| `gt` | Greater than | `->gt(10)` |
| `lt` | Less than | `->lt(100)` |
| `between` | Range | `->between(10, 100)` |
| `contains` | Text contains | `->contains('search')` |

See [Match Modes](./03-match-modes.md) for the complete list.

## Next Steps

- [Filter Types](./02-filter-types.md) - Learn about all filter types
- [Match Modes](./03-match-modes.md) - All 17 match modes explained
- [Filter Selections](./04-filter-selections.md) - Group filters with AND/OR logic
- [Relation Filters](./05-relation-filters.md) - Filter through relationships
