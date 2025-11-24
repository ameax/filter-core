# Filter Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ameax/filter-core.svg?style=flat-square)](https://packagist.org/packages/ameax/filter-core)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ameax/filter-core/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ameax/filter-core/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ameax/filter-core/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ameax/filter-core/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ameax/filter-core.svg?style=flat-square)](https://packagist.org/packages/ameax/filter-core)

A powerful, type-safe filtering system for Laravel applications. Filter Core provides a clean API for building complex database queries with automatic value sanitization, validation, and support for relations.

## Features

- **Multiple Filter Types**: Boolean, Integer, Text, Select filters
- **Rich Match Modes**: IS, IS_NOT, ANY, NONE, CONTAINS, GREATER_THAN, LESS_THAN, BETWEEN, EMPTY, NOT_EMPTY
- **Relation Filtering**: Filter through Eloquent relationships with `whereHas()`
- **Collection Filtering**: Apply the same filter logic to in-memory Collections
- **Value Sanitization**: Automatic conversion of input values (e.g., `"true"` → `true`)
- **Value Validation**: Laravel validation rules with descriptive error messages
- **Type-Safe Values**: Strict typing with `typedValue()` methods
- **Filter Selections**: Group and persist filter configurations as JSON
- **Dynamic Filters**: Create filters at runtime without class definitions
- **Fluent API**: Clean, chainable syntax for building filters

## Installation

```bash
composer require ameax/filter-core
```

## Quick Start

### 1. Create a Filter Class

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

### 2. Add the Filterable Trait to Your Model

```php
use Ameax\FilterCore\Concerns\Filterable;

class User extends Model
{
    use Filterable;

    protected function filterResolver(): array
    {
        return [
            StatusFilter::class,
        ];
    }
}
```

### 3. Apply Filters

```php
// Simple filter
$users = User::query()
    ->applyFilter(StatusFilter::value()->is('active'))
    ->get();

// Multiple filters
$users = User::query()
    ->applyFilters([
        StatusFilter::value()->any(['active', 'pending']),
        AgeFilter::value()->greaterThan(18),
    ])
    ->get();
```

## Filter Types

### BooleanFilter

```php
class IsActiveFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'is_active';
    }
}

// Usage
IsActiveFilter::value()->is(true);
```

**Sanitization**: Converts `"true"`, `"1"`, `"yes"`, `"on"` to `true`; `"false"`, `"0"`, `"no"`, `"off"` to `false`.

### IntegerFilter

```php
class AgeFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'age';
    }
}

// Usage
AgeFilter::value()->is(25);
AgeFilter::value()->greaterThan(18);
AgeFilter::value()->between(18, 65);
```

**Match Modes**: IS, IS_NOT, GREATER_THAN, LESS_THAN, BETWEEN

### TextFilter

```php
class NameFilter extends TextFilter
{
    public function column(): string
    {
        return 'name';
    }
}

// Usage
NameFilter::value()->contains('John');
NameFilter::value()->is('John Doe');
```

**Match Modes**: CONTAINS, IS, IS_NOT

### SelectFilter

```php
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
        ];
    }
}

// Usage
StatusFilter::value()->is('active');
StatusFilter::value()->any(['active', 'pending']);
StatusFilter::value()->none(['inactive']);
```

**Match Modes**: IS, IS_NOT, ANY, NONE

## Relation Filters

Filter through Eloquent relationships:

```php
class CompanyNameFilter extends TextFilter
{
    public function column(): string
    {
        return 'name';
    }
}

// In your model's filterResolver:
protected function filterResolver(): array
{
    return [
        CompanyNameFilter::via('company'), // Filter users by company.name
    ];
}

// Usage - finds users whose company name contains "Acme"
User::applyFilter(CompanyNameFilter::value()->contains('Acme'))->get();
```

## Dynamic Filters

Create filters at runtime without class definitions:

```php
use Ameax\FilterCore\Filters\SelectFilter;
use Ameax\FilterCore\Filters\IntegerFilter;

$statusFilter = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withLabel('Status')
    ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']);

$ageFilter = IntegerFilter::dynamic('age')
    ->withColumn('age')
    ->withLabel('Age');

$results = QueryApplicator::for(User::query())
    ->withFilters([$statusFilter, $ageFilter])
    ->applyFilters([
        new FilterValue('status', MatchModeEnum::IS, 'active'),
        new FilterValue('age', MatchModeEnum::GREATER_THAN, 18),
    ])
    ->getQuery()
    ->get();
```

## Collection Filtering

Apply filters to in-memory Collections with the same logic as query filtering:

```php
use Ameax\FilterCore\Collection\CollectionApplicator;
use Ameax\FilterCore\Selections\FilterSelection;

// Get a collection
$users = User::all();

// Simple filter via model
$activeUsers = User::filterCollection($users, [
    StatusFilter::value()->is('active'),
]);

// With FilterSelection (supports AND/OR logic)
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

$filtered = User::filterCollectionWithSelection($users, $selection);

// Direct use of CollectionApplicator
$filtered = CollectionApplicator::for($users)
    ->withFilters([StatusFilter::class, AgeFilter::class])
    ->applyFilters([
        StatusFilter::value()->is('active'),
        AgeFilter::value()->greaterThan(18),
    ])
    ->getCollection();
```

Collection filtering produces the same results as query filtering, making it useful for:
- Filtering already-loaded data without additional database queries
- Unit testing filter logic
- Processing data from external sources

## Filter Selections

Group filters for persistence and reuse:

```php
use Ameax\FilterCore\Selections\FilterSelection;

// Create a selection
$selection = FilterSelection::make('Premium Users')
    ->description('Active users with high engagement')
    ->where(StatusFilter::class)->is('active')
    ->where(AgeFilter::class)->greaterThan(18)
    ->where(ScoreFilter::class)->between(80, 100);

// Apply to query
$users = User::query()->applyFilters($selection)->get();

// Serialize to JSON
$json = $selection->toJson();

// Restore from JSON
$restored = FilterSelection::fromJson($json);
```

## Value Sanitization & Validation

Filters automatically sanitize and validate input values:

### Sanitization

```php
// BooleanFilter: strings converted to booleans
$filter->sanitizeValue('true', MatchModeEnum::IS);  // Returns: true
$filter->sanitizeValue('1', MatchModeEnum::IS);     // Returns: true
$filter->sanitizeValue('yes', MatchModeEnum::IS);   // Returns: true

// IntegerFilter: strings converted to integers
$filter->sanitizeValue('123', MatchModeEnum::IS);   // Returns: 123

// IntegerFilter: arrays converted to BetweenValue for BETWEEN mode
$filter->sanitizeValue(['min' => 5, 'max' => 10], MatchModeEnum::BETWEEN);
// Returns: BetweenValue(min: 5, max: 10)

// TextFilter: whitespace trimmed
$filter->sanitizeValue('  hello  ', MatchModeEnum::CONTAINS);  // Returns: 'hello'
```

### Validation

```php
use Ameax\FilterCore\Exceptions\FilterValidationException;

try {
    User::applyFilter(StatusFilter::value()->is('invalid_status'))->get();
} catch (FilterValidationException $e) {
    $e->getFilterKey();     // 'StatusFilter'
    $e->getErrors();        // ['value' => ['The selected value is invalid.']]
    $e->getFirstErrors();   // ['The selected value is invalid.']
}
```

### Custom Validation Rules

Override `validationRules()` in your filter:

```php
class AgeFilter extends IntegerFilter
{
    public function validationRules(MatchModeEnum $mode): array
    {
        return [
            'value' => 'required|numeric|min:0|max:150',
        ];
    }
}
```

## Type-Safe Values

Use `typedValue()` for strict typing:

```php
// BooleanFilter: expects bool
$filter->typedValue(true);    // OK
$filter->typedValue('yes');   // TypeError!

// IntegerFilter: expects int or BetweenValue
$filter->typedValue(42);                        // OK
$filter->typedValue(new BetweenValue(1, 10));   // OK
$filter->typedValue('42');                      // TypeError!
```

### BetweenValue DTO

Type-safe representation of range values:

```php
use Ameax\FilterCore\Data\BetweenValue;

// Create directly
$between = new BetweenValue(min: 10, max: 100);

// Create from array
$between = BetweenValue::fromArray(['min' => 10, 'max' => 100]);
$between = BetweenValue::fromArray([10, 100]);  // Indexed array

// Access values
$between->min;  // 10
$between->max;  // 100

// Convert to array
$between->toArray();  // ['min' => 10, 'max' => 100]
```

## Custom Filter Logic

Override `apply()` for complex query logic:

```php
class FullNameFilter extends TextFilter
{
    public function column(): string
    {
        return 'first_name'; // Not used when apply() is overridden
    }

    public function apply(Builder|QueryBuilder $query, MatchModeEnum $mode, mixed $value): bool
    {
        if ($mode === MatchModeEnum::CONTAINS) {
            $query->where(function ($q) use ($value) {
                $q->where('first_name', 'like', "%{$value}%")
                  ->orWhere('last_name', 'like', "%{$value}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
            });
            return true; // Custom logic applied
        }

        return false; // Use default logic
    }
}
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
