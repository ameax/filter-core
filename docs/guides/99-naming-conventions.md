# Naming Conventions

This document describes the naming conventions used throughout filter-core.

## Method Naming

### Getters

| Pattern | Usage | Examples |
|---------|-------|----------|
| `property()` | Simple properties | `column()`, `nullable()`, `label()` |
| `getProperty()` | Computed/resolved values | `getKey()`, `getRelation()`, `getRelationMode()` |

**Rule of thumb:**
- Use property name directly for simple stored values
- Use `get` prefix for computed values or when resolving dynamic behavior

### Setters (Fluent)

All setters use the `with` prefix and return `$this` for chaining:

```php
DateFilter::dynamic('created_at')
    ->withColumn('created_at')
    ->withLabel('Created Date')
    ->withNullable(true)
    ->withTime();
```

### Boolean Methods

| Pattern | Usage | Examples |
|---------|-------|----------|
| `isProperty()` | Check state | `isEmpty()`, `isClosed()` |
| `hasProperty()` | Check existence | `hasFilters()`, `hasTime()`, `hasAppliedFilters()` |
| `property()` | Simple boolean property | `nullable()` |

### Factory Methods

| Pattern | Usage | Examples |
|---------|-------|----------|
| `make()` | Create instance | `Filter::make()`, `FilterSelection::make()` |
| `create()` | Create with key | `DynamicDateFilter::create('key')` |
| `for()` | Create for context | `QueryApplicator::for($query)` |
| `from*()` | Create from data | `fromArray()`, `fromJson()` |

### Conversion Methods

| Pattern | Usage | Examples |
|---------|-------|----------|
| `toArray()` | Convert to array | `$selection->toArray()` |
| `toJson()` | Convert to JSON | `$selection->toJson()` |
| `toSql()` | Convert to SQL | `$selection->toSql($query)` |
| `toLabel()` | Convert to label | `$dateRange->toLabel()` |
| `toDefinition()` | Convert to definition | `$filter->toDefinition()` |

### Query Scopes (Eloquent)

Use `scope` prefix for Eloquent model scopes:

```php
public function scopeActive(Builder $query): Builder
public function scopeForScope(Builder $query, string $scope): Builder
public function scopeOrdered(Builder $query): Builder
```

## Class Naming

| Type | Pattern | Examples |
|------|---------|----------|
| Filters | `*Filter` | `DateFilter`, `SelectFilter`, `DecimalFilter` |
| Dynamic Filters | `Dynamic*Filter` | `DynamicDateFilter`, `DynamicSelectFilter` |
| Match Modes | `*MatchMode` | `DateRangeMatchMode`, `ContainsMatchMode` |
| Enums | `*Enum` | `FilterTypeEnum`, `GroupOperatorEnum` |
| DTOs | Descriptive | `FilterValue`, `BetweenValue`, `ResolvedDateRange` |
| Exceptions | `*Exception` | `FilterValidationException` |

## Property Naming

| Type | Convention | Examples |
|------|------------|----------|
| Boolean | Descriptive, no `is` prefix | `$nullable`, `$isActive` (DB column) |
| Collections | Plural | `$filters`, `$items`, `$appliedFilters` |
| Single items | Singular | `$filter`, `$value`, `$query` |

## Configuration Keys

Use snake_case for configuration keys:

```php
'filter-core.timezone'
'filter-core.user_model'
```

## Database Columns

Use snake_case for database columns:

```php
'date_range_config'
'is_active'
'sort_order'
'created_at'
```
