# Filter Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ameax/filter-core.svg?style=flat-square)](https://packagist.org/packages/ameax/filter-core)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ameax/filter-core/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ameax/filter-core/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ameax/filter-core/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ameax/filter-core/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ameax/filter-core.svg?style=flat-square)](https://packagist.org/packages/ameax/filter-core)

A powerful, type-safe filtering system for Laravel applications. Filter Core provides a clean API for building complex database queries with automatic value sanitization, validation, and support for relations.

## Features

- **4 Filter Types**: Boolean, Integer, Text, Select filters
- **17 Match Modes**: IS, IS_NOT, ANY, NONE, CONTAINS, GT, LT, BETWEEN, REGEX, EMPTY, and more
- **AND/OR Logic**: Complex nested filter groups with FilterSelection
- **Relation Filtering**: Filter through Eloquent relationships with `whereHas()`
- **Collection Filtering**: Apply the same filter logic to in-memory Collections
- **Value Sanitization**: Automatic conversion of input values (e.g., `"true"` → `true`)
- **Value Validation**: Laravel validation rules with descriptive error messages
- **Dynamic Filters**: Create filters at runtime without class definitions
- **JSON Serialization**: Persist and restore filter configurations

## Installation

```bash
composer require ameax/filter-core
```

## Quick Start

### 1. Create a Filter

```php
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

### 2. Add Filterable Trait to Model

```php
use Ameax\FilterCore\Concerns\Filterable;

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

### 3. Apply Filters

```php
use Ameax\FilterCore\Data\FilterValue;

// Simple filter
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->is('active'))
    ->get();

// Multiple filters with AND
$users = User::query()
    ->applyFilters([
        FilterValue::for(StatusFilter::class)->any(['active', 'pending']),
        FilterValue::for(CountFilter::class)->gt(10),
    ])
    ->get();
```

### 4. Use Filter Selections for Complex Logic

```php
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Selections\FilterGroup;

// AND logic (default)
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

// OR logic
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

// Nested: count > 10 AND (status = 'active' OR status = 'pending')
$selection = FilterSelection::make()
    ->where(CountFilter::class)->gt(10)
    ->orWhere(function (FilterGroup $g) {
        $g->where(StatusFilter::class)->is('active');
        $g->where(StatusFilter::class)->is('pending');
    });

$users = User::query()->applySelection($selection)->get();
```

## Documentation

| Guide | Description |
|-------|-------------|
| [Getting Started](docs/todos/01-getting-started.md) | Installation and basic setup |
| [Filter Types](docs/todos/02-filter-types.md) | SelectFilter, IntegerFilter, TextFilter, BooleanFilter |
| [Match Modes](docs/todos/03-match-modes.md) | All 17 match modes explained |
| [Filter Selections](docs/todos/04-filter-selections.md) | AND/OR logic with nested groups |
| [Relation Filters](docs/todos/05-relation-filters.md) | Filter through relationships |
| [Collection Filtering](docs/todos/06-collection-filtering.md) | In-memory collection filtering |
| [Dynamic Filters](docs/todos/07-dynamic-filters.md) | Runtime filter creation |
| [Validation](docs/todos/08-validation-sanitization.md) | Input validation and sanitization |
| [Advanced Usage](docs/todos/09-advanced-usage.md) | Custom logic and extensibility |

## Quick Reference

### Filter Types

| Type | Use Case | Key Modes |
|------|----------|-----------|
| `SelectFilter` | Predefined options | `is`, `any`, `none` |
| `IntegerFilter` | Numeric values | `gt`, `lt`, `between` |
| `TextFilter` | Text search | `contains`, `startsWith`, `regex` |
| `BooleanFilter` | True/False | `is` |

### Match Modes

```php
// Equality
->is('value')           // = value
->isNot('value')        // != value

// Sets
->any(['a', 'b'])       // IN (a, b)
->none(['a', 'b'])      // NOT IN (a, b)

// Comparison
->gt(10)                // > 10
->gte(10)               // >= 10
->lt(100)               // < 100
->lte(100)              // <= 100
->between(10, 100)      // BETWEEN 10 AND 100

// Text
->contains('text')      // LIKE %text%
->startsWith('pre')     // LIKE pre%
->endsWith('fix')       // LIKE %fix
->regex('^pattern')     // REGEXP

// Null
->empty()               // IS NULL
->notEmpty()            // IS NOT NULL
```

### Relation Filters

```php
// In filterResolver
protected static function filterResolver(): \Closure
{
    return fn () => [
        StatusFilter::class,                    // Direct filter
        CompanyStatusFilter::via('company'),    // Filter through relation
    ];
}

// Usage
User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->is('active'))
    ->get();
```

### Dynamic Filters

```php
use Ameax\FilterCore\Filters\SelectFilter;

$filter = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']);
```

### JSON Serialization

```php
// Save
$json = $selection->toJson();

// Load
$selection = FilterSelection::fromJson($json);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Michael Schmidt](https://github.com/69188126+ms-aranes)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
