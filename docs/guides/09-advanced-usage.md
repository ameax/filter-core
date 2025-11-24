# Advanced Usage

Custom filter logic, custom match modes, and advanced extensibility patterns.

## Custom Filter Logic

Override the `apply()` method to implement custom query logic:

```php
use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Filters\TextFilter;
use Ameax\FilterCore\MatchModes\ContainsMatchMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FullNameFilter extends TextFilter
{
    public function column(): string
    {
        return 'first_name'; // Not used when apply() is overridden
    }

    /**
     * Custom apply logic for searching across multiple columns.
     *
     * @return bool True if custom logic was applied, false for default behavior
     */
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        if ($mode instanceof ContainsMatchMode) {
            $query->where(function ($q) use ($value) {
                $q->where('first_name', 'like', "%{$value}%")
                  ->orWhere('last_name', 'like', "%{$value}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"]);
            });

            return true; // Custom logic applied
        }

        return false; // Use default MatchMode logic
    }
}
```

### Return Value

- Return `true` if you handled the query logic
- Return `false` to fall back to the default `MatchMode::apply()` behavior

### Mode-Specific Custom Logic

```php
public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
{
    // Custom logic only for specific modes
    if ($mode instanceof ContainsMatchMode) {
        // Custom contains logic
        return true;
    }

    if ($mode instanceof IsMatchMode) {
        // Custom exact match logic
        return true;
    }

    // Default behavior for other modes
    return false;
}
```

## Custom Match Modes

Create custom match modes by implementing `MatchModeContract`:

### 1. Implement the Interface

```php
<?php

namespace App\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CaseInsensitiveLikeMatchMode implements MatchModeContract
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
        // PostgreSQL ILIKE or MySQL LOWER()
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

### 2. Register the Mode

```php
use Ameax\FilterCore\MatchModes\MatchMode;
use App\MatchModes\CaseInsensitiveLikeMatchMode;

// In a service provider
public function boot(): void
{
    MatchMode::register('ilike', CaseInsensitiveLikeMatchMode::class);
}
```

### 3. Use the Mode

```php
use Ameax\FilterCore\MatchModes\MatchMode;

// Via factory
$mode = MatchMode::get('ilike');

// In FilterValue
$filterValue = new FilterValue('NameFilter', MatchMode::get('ilike'), 'john');
```

### Example: JSON Contains Mode

```php
class JsonContainsMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'json_contains';
    }

    public function label(): string
    {
        return 'JSON Contains';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // MySQL JSON_CONTAINS
        $query->whereRaw("JSON_CONTAINS({$column}, ?)", [json_encode($value)]);
    }

    public function matchesCollection(mixed $itemValue, mixed $filterValue): bool
    {
        $array = is_array($itemValue) ? $itemValue : json_decode($itemValue, true);
        return in_array($filterValue, $array ?? []);
    }
}
```

## Filter with Custom Allowed Modes

Restrict which match modes a filter allows:

```php
use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Filters\SelectFilter;
use Ameax\FilterCore\MatchModes\MatchMode;

class StrictStatusFilter extends SelectFilter
{
    public function column(): string
    {
        return 'status';
    }

    public function options(): array
    {
        return ['active' => 'Active', 'inactive' => 'Inactive'];
    }

    /**
     * Only allow exact match - no ANY/NONE
     */
    public function allowedModes(): array
    {
        return [
            MatchMode::is(),
            MatchMode::isNot(),
        ];
    }

    public function defaultMode(): MatchModeContract
    {
        return MatchMode::is();
    }
}
```

## Extending Base Filters

Create reusable filter base classes:

```php
<?php

namespace App\Filters\Base;

use Ameax\FilterCore\Filters\SelectFilter;

abstract class EnumFilter extends SelectFilter
{
    abstract protected function enumClass(): string;

    public function options(): array
    {
        $enum = $this->enumClass();

        return collect($enum::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->name])
            ->all();
    }
}

// Usage
class OrderStatusFilter extends EnumFilter
{
    public function column(): string
    {
        return 'status';
    }

    protected function enumClass(): string
    {
        return \App\Enums\OrderStatus::class;
    }
}
```

## Computed/Virtual Filters

Filters that don't map directly to a column:

```php
class HasRecentOrderFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'id'; // Not actually used
    }

    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        if ($value === true) {
            $query->whereHas('orders', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            });
        } else {
            $query->whereDoesntHave('orders', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            });
        }

        return true;
    }
}
```

## Filter Composition

Combine multiple conditions in a single filter:

```php
class PremiumUserFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'id';
    }

    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        if ($value === true) {
            $query->where(function ($q) {
                $q->where('subscription_type', 'premium')
                  ->orWhere('lifetime_value', '>', 10000);
            });
        } else {
            $query->where('subscription_type', '!=', 'premium')
                  ->where('lifetime_value', '<=', 10000);
        }

        return true;
    }
}
```

## Filter Scopes

Add reusable filter scopes to your models:

```php
class User extends Model
{
    use Filterable;

    // Predefined filter selections
    public function scopeActive($query)
    {
        return $query->applyFilter(
            FilterValue::for(StatusFilter::class)->is('active')
        );
    }

    public function scopePremium($query)
    {
        return $query->applyFilter(
            FilterValue::for(SubscriptionFilter::class)->any(['premium', 'enterprise'])
        );
    }

    public function scopeHighValue($query)
    {
        return $query->applyFilters([
            FilterValue::for(OrderCountFilter::class)->gt(10),
            FilterValue::for(TotalSpentFilter::class)->gt(1000),
        ]);
    }
}

// Usage
User::active()->premium()->get();
User::highValue()->get();
```

## Filter Events/Hooks

Add hooks before/after filter application:

```php
class AuditedFilter extends SelectFilter
{
    public function column(): string
    {
        return 'status';
    }

    public function options(): array
    {
        return ['active' => 'Active', 'inactive' => 'Inactive'];
    }

    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        // Before hook
        Log::info('Applying filter', [
            'filter' => static::key(),
            'mode' => $mode->key(),
            'value' => $value,
        ]);

        // Apply default logic
        $result = parent::apply($query, $mode, $value);

        // After hook
        event(new FilterApplied(static::key(), $mode, $value));

        return $result;
    }
}
```

## Testing Filters

### Unit Test Filter Logic

```php
use Ameax\FilterCore\Collection\CollectionApplicator;

public function test_status_filter_returns_active_only()
{
    $collection = collect([
        (object) ['id' => 1, 'status' => 'active'],
        (object) ['id' => 2, 'status' => 'inactive'],
        (object) ['id' => 3, 'status' => 'active'],
    ]);

    $filtered = CollectionApplicator::for($collection)
        ->withFilters([StatusFilter::class])
        ->applyFilter(FilterValue::for(StatusFilter::class)->is('active'))
        ->getCollection();

    $this->assertCount(2, $filtered);
    $this->assertEquals([1, 3], $filtered->pluck('id')->all());
}
```

### Test Custom Apply Logic

```php
public function test_full_name_filter_searches_both_columns()
{
    $user1 = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $user2 = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Johnson']);
    $user3 = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Smith']);

    $result = User::query()
        ->applyFilter(FilterValue::for(FullNameFilter::class)->contains('John'))
        ->get();

    $this->assertCount(2, $result);
    $this->assertTrue($result->contains($user1));
    $this->assertTrue($result->contains($user2));
}
```

### Test Validation

```php
public function test_invalid_status_throws_validation_exception()
{
    $this->expectException(FilterValidationException::class);

    User::query()
        ->applyFilter(FilterValue::for(StatusFilter::class)->is('invalid_status'))
        ->get();
}
```

## Performance Optimization

### Indexed Columns

Ensure filtered columns are indexed:

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->index('status');
    $table->index(['status', 'created_at']); // Compound for combined filters
});
```

### Avoid N+1 with Relations

```php
// Bad: N+1 queries
$users = User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->is('active'))
    ->get();

foreach ($users as $user) {
    echo $user->company->name; // N+1!
}

// Good: Eager load
$users = User::query()
    ->applyFilter(FilterValue::for(CompanyStatusFilter::class)->is('active'))
    ->with('company')
    ->get();
```

### Query Optimization

```php
class OptimizedStatusFilter extends SelectFilter
{
    public function apply(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value): bool
    {
        if ($mode instanceof AnyMatchMode && count($value) > 10) {
            // For large IN clauses, consider a join or temp table
            $query->whereIn($this->column(), $value);
            return true;
        }

        return false; // Default for small sets
    }
}
```

## Debugging

### Log Generated SQL

```php
$query = User::query()
    ->applyFilters([
        FilterValue::for(StatusFilter::class)->is('active'),
        FilterValue::for(CountFilter::class)->gt(10),
    ]);

// Log the SQL
Log::debug($query->toSql(), $query->getBindings());

// Or use Laravel Debugbar
$users = $query->get();
```

### Inspect Filter Application

```php
use Ameax\FilterCore\Query\QueryApplicator;

$applicator = QueryApplicator::for(User::query())
    ->withFilters([StatusFilter::class, CountFilter::class]);

// Check registered filters
$filters = $applicator->getFilters();

// Apply and inspect
$applicator->applyFilter($filterValue);
$query = $applicator->getQuery();

dd($query->toSql());
```
