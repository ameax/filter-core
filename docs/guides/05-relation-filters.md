# Relation Filters

Filter through Eloquent relationships using `whereHas()` and `whereDoesntHave()`.

## Overview

Relation filters allow you to filter a model based on properties of its related models. For example, filter users by their company's name, or products by their category's status.

## Setup

### 1. Define the Filter

Create a filter for the related model's column:

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\SelectFilter;

class CompanyStatusFilter extends SelectFilter
{
    public function column(): string
    {
        return 'status'; // Column on the Company model
    }

    public function options(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}
```

### 2. Register with Relation

In your model's `filterResolver()`, use `via()` to specify the relationship:

```php
<?php

namespace App\Models;

use Ameax\FilterCore\Concerns\Filterable;
use App\Filters\CompanyStatusFilter;
use App\Filters\UserStatusFilter;

class User extends Model
{
    use Filterable;

    protected static function filterResolver(): \Closure
    {
        return fn () => [
            UserStatusFilter::class,                    // Direct filter
            CompanyStatusFilter::via('company'),        // Relation filter
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

### 3. Apply the Filter

```php
use Ameax\FilterCore\Data\FilterValue;
use App\Filters\CompanyStatusFilter;

// Find users whose company has status = 'active'
$users = User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->is('active'))
    ->get();

// SQL: WHERE EXISTS (
//   SELECT * FROM companies
//   WHERE users.company_id = companies.id
//   AND companies.status = 'active'
// )
```

## Relation Modes

### `via()` - Records WITH Matching Relation

The default mode uses `whereHas()` to find records that HAVE a related record matching the condition:

```php
CompanyStatusFilter::via('company')

// Usage
FilterValue::for(CompanyStatusFilter::class)->is('active')

// Finds: Users who have a company with status = 'active'
// Excludes: Users without a company (company_id = null)
```

### `viaDoesntHave()` - Records WITHOUT Matching Relation

Uses `whereDoesntHave()` to find records that DON'T have a matching relation:

```php
CompanyStatusFilter::viaDoesntHave('company')

// Usage
FilterValue::for(CompanyStatusFilter::class)->is('active')

// Finds: Users who don't have an 'active' company
// Includes: Users without a company
// Includes: Users with a company that has status != 'active'
```

### `withoutRelation()` - Records WITHOUT Any Relation

A special mode to find records that have NO related record at all:

```php
// In filterResolver
SomeFilter::withoutRelation('company')

// Finds: Users where company_id IS NULL or no matching company exists
```

## Important Behavior

### Null Relations are Excluded

When using `via()`, records without the relationship are **never included**:

```php
// User without company (company_id = null) is NEVER returned
$users = User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->any(['active', 'inactive', 'pending']))
    ->get();

// Even filtering for all possible values won't include users without companies
```

To include records without relationships, you need OR logic:

```php
use Ameax\FilterCore\Selections\FilterSelection;

$selection = FilterSelection::makeOr()
    ->where(CompanyStatusFilter::class)->is('active')   // Has active company
    ->where(HasCompanyFilter::class)->is(false);        // OR has no company
```

### Nested Relationships

Use dot notation for nested relationships:

```php
// Filter users by their company's country name
CountryNameFilter::via('company.country')

// SQL uses nested whereHas:
// WHERE EXISTS (
//   SELECT * FROM companies WHERE users.company_id = companies.id
//   AND EXISTS (
//     SELECT * FROM countries WHERE companies.country_id = countries.id
//     AND countries.name = 'Germany'
//   )
// )
```

## Dynamic Relation Filters

Create relation filters at runtime:

```php
use Ameax\FilterCore\Filters\SelectFilter;

$filter = SelectFilter::dynamic('company_status')
    ->withColumn('status')
    ->withRelation('company')
    ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']);

$users = QueryApplicator::for(User::query())
    ->withFilters([$filter])
    ->applyFilter(FilterValue::make('company_status', MatchMode::is(), 'active'))
    ->getQuery()
    ->get();
```

## Combining Direct and Relation Filters

Mix direct model filters with relation filters:

```php
use Ameax\FilterCore\Selections\FilterSelection;

// Find active users in active companies
$selection = FilterSelection::make()
    ->where(UserStatusFilter::class)->is('active')       // Direct: user.status
    ->where(CompanyStatusFilter::class)->is('active');   // Relation: company.status

$users = User::query()->applySelection($selection)->get();

// SQL: WHERE users.status = 'active'
//   AND EXISTS (SELECT * FROM companies WHERE ... AND companies.status = 'active')
```

## Relation Filters with OR Logic

```php
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Selections\FilterGroup;

// Users in fresh OR brackish water ponds (assuming Koi -> Pond relation)
$selection = FilterSelection::makeOr()
    ->where(PondWaterTypeFilter::class)->is('fresh')
    ->where(PondWaterTypeFilter::class)->is('brackish');

$kois = Koi::query()->applySelection($selection)->get();
```

## Multiple Relations

A model can have filters for multiple relationships:

```php
protected static function filterResolver(): \Closure
{
    return fn () => [
        // Direct filters
        UserStatusFilter::class,
        UserNameFilter::class,

        // Different relation filters
        CompanyStatusFilter::via('company'),
        CompanyNameFilter::via('company'),
        DepartmentNameFilter::via('department'),
        TeamSizeFilter::via('team'),
    ];
}
```

## RelationModeEnum

Internally, relation mode is tracked via `RelationModeEnum`:

| Mode | Method | Behavior |
|------|--------|----------|
| `HAS` | `via()` | `whereHas()` - records WITH matching relation |
| `DOESNT_HAVE` | `viaDoesntHave()` | `whereDoesntHave()` - records WITHOUT matching relation |

## Performance Considerations

Relation filters use subqueries (`EXISTS`), which can be slower than direct filters:

1. **Index the foreign keys** - Ensure `company_id` etc. are indexed
2. **Index filtered columns** - Index `companies.status` if frequently filtered
3. **Consider denormalization** - For high-traffic filters, denormalize to the parent table
4. **Use eager loading** - If you'll access the relation after filtering:

```php
$users = User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->is('active'))
    ->with('company') // Eager load to avoid N+1
    ->get();
```

## Validation

Relation filters are validated the same as direct filters:

```php
// Throws FilterValidationException if 'invalid_status' is not in options
User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->is('invalid_status'))
    ->get();
```

## Next Steps

- [Collection Filtering](./06-collection-filtering.md) - Filter in-memory collections
- [Filter Selections](./04-filter-selections.md) - Complex AND/OR logic
