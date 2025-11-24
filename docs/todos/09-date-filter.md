# TODO: DateFilter Type

**Priority:** High
**Status:** Open

## Problem

No built-in filter type for date columns (`DATE`). Users must create custom implementations or use TextFilter with string dates.

## Proposed Solution

Add `DateFilter` base class for filtering date columns.

### Implementation

```php
namespace Ameax\FilterCore\Filters;

abstract class DateFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATE;
    }

    public function allowedMatchModes(): array
    {
        return [
            new IsMatchMode(),
            new IsNotMatchMode(),
            new BetweenMatchMode(),
            new BeforeMatchMode(),        // NEW: < date
            new AfterMatchMode(),          // NEW: > date
            new OnOrBeforeMatchMode(),     // NEW: <= date
            new OnOrAfterMatchMode(),      // NEW: >= date
            new EmptyMatchMode(),
            new NotEmptyMatchMode(),
        ];
    }

    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->toDateString(); // 'Y-m-d'
        }

        if (is_string($value)) {
            try {
                return \Carbon\Carbon::parse($value)->toDateString();
            } catch (\Exception $e) {
                return $value; // Let validation handle it
            }
        }

        if (is_array($value)) {
            // For between mode
            return array_map(fn($v) => $this->sanitizeValue($v, $mode), $value);
        }

        return $value;
    }

    public function validationRules(MatchModeContract $mode): array
    {
        if ($mode instanceof BetweenMatchMode) {
            return [
                'value' => ['required', 'array', 'size:2'],
                'value.*' => ['date_format:Y-m-d'],
            ];
        }

        if ($mode instanceof EmptyMatchMode || $mode instanceof NotEmptyMatchMode) {
            return [];
        }

        return [
            'value' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Optional: Type-safe value accessor
     */
    public function typedValue(string|array $value): \Carbon\Carbon|array
    {
        if (is_array($value)) {
            return array_map(fn($v) => \Carbon\Carbon::parse($v), $value);
        }

        return \Carbon\Carbon::parse($value);
    }
}
```

### New Match Modes

```php
// src/MatchModes/BeforeMatchMode.php
class BeforeMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'before';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->where($column, '<', $value);
    }
}

// src/MatchModes/AfterMatchMode.php
class AfterMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'after';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->where($column, '>', $value);
    }
}

// src/MatchModes/OnOrBeforeMatchMode.php
class OnOrBeforeMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'on_or_before';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->where($column, '<=', $value);
    }
}

// src/MatchModes/OnOrAfterMatchMode.php
class OnOrAfterMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'on_or_after';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->where($column, '>=', $value);
    }
}
```

### Relative Date Match Modes

For user-friendly date filtering with relative expressions like "older than 30 days" or "last year":

```php
// src/MatchModes/OlderThanMatchMode.php
class OlderThanMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'older_than';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // $value = ['amount' => 30, 'unit' => 'days']
        $date = Carbon::now()->sub($value['amount'], $value['unit']);
        $query->where($column, '<', $date->toDateString());
    }
}

// src/MatchModes/NewerThanMatchMode.php
class NewerThanMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'newer_than';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // $value = ['amount' => 30, 'unit' => 'days']
        $date = Carbon::now()->sub($value['amount'], $value['unit']);
        $query->where($column, '>', $date->toDateString());
    }
}

// src/MatchModes/InLastMatchMode.php
class InLastMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'in_last';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // $value = ['amount' => 30, 'unit' => 'days']
        $date = Carbon::now()->sub($value['amount'], $value['unit']);
        $query->where($column, '>=', $date->toDateString())
              ->where($column, '<=', Carbon::now()->toDateString());
    }
}

// src/MatchModes/PeriodMatchMode.php
class PeriodMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'period';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // $value = 'last_year' | 'this_month' | 'yesterday' | 'today' | 'tomorrow'
        [$start, $end] = $this->resolvePeriod($value);

        $query->whereBetween($column, [$start->toDateString(), $end->toDateString()]);
    }

    protected function resolvePeriod(string $period): array
    {
        return match($period) {
            'today' => [Carbon::today(), Carbon::today()],
            'yesterday' => [Carbon::yesterday(), Carbon::yesterday()],
            'tomorrow' => [Carbon::tomorrow(), Carbon::tomorrow()],
            'this_week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'last_week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            'next_week' => [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'next_month' => [Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            'last_year' => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()],
            'next_year' => [Carbon::now()->addYear()->startOfYear(), Carbon::now()->addYear()->endOfYear()],
            default => throw new \InvalidArgumentException("Unknown period: {$period}"),
        };
    }
}
```

### Usage Example

```php
class CreatedAtFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function label(): string
    {
        return 'Created Date';
    }

    public function allowedMatchModes(): array
    {
        return [
            ...parent::allowedMatchModes(),
            new OlderThanMatchMode(),
            new NewerThanMatchMode(),
            new InLastMatchMode(),
            new PeriodMatchMode(),
        ];
    }
}

// Absolute dates
$users = User::query()
    ->applyFilters([
        FilterValue::for(CreatedAtFilter::class)->between('2024-01-01', '2024-12-31'),
        FilterValue::for(CreatedAtFilter::class)->after('2024-06-01'),
        FilterValue::for(CreatedAtFilter::class)->onOrBefore('2024-12-31'),
    ])
    ->get();

// Relative dates
$users = User::query()
    ->applyFilters([
        // Older than 30 days
        FilterValue::for(CreatedAtFilter::class)
            ->olderThan(['amount' => 30, 'unit' => 'days']),

        // In the last 7 days
        FilterValue::for(CreatedAtFilter::class)
            ->inLast(['amount' => 7, 'unit' => 'days']),

        // Last year
        FilterValue::for(CreatedAtFilter::class)
            ->period('last_year'),

        // This month
        FilterValue::for(CreatedAtFilter::class)
            ->period('this_month'),

        // Today
        FilterValue::for(CreatedAtFilter::class)
            ->period('today'),
    ])
    ->get();
```

### Dynamic Filter Support

```php
$filter = DateFilter::dynamic('created_at')
    ->withColumn('created_at')
    ->withLabel('Created Date');

$selection = FilterSelection::make()
    ->where($filter)->between('2024-01-01', '2024-12-31');
```

## Implementation Steps

1. Add `FilterTypeEnum::DATE` case
2. Create `DateFilter` base class
3. Create 4 new absolute match modes (Before, After, OnOrBefore, OnOrAfter)
4. Create 4 new relative match modes (OlderThan, NewerThan, InLast, Period)
5. Add `DynamicDateFilter` class
6. Add comprehensive tests:
   - Date sanitization (string, Carbon, null)
   - All absolute match modes
   - All relative match modes
   - Between with date range
   - Period resolution (today, yesterday, last_month, last_year, etc.)
   - Validation rules
   - Dynamic filter
7. Add to documentation

## Related Files

- `src/Filters/DateFilter.php` (NEW)
- `src/Filters/Dynamic/DynamicDateFilter.php` (NEW)
- `src/MatchModes/BeforeMatchMode.php` (NEW)
- `src/MatchModes/AfterMatchMode.php` (NEW)
- `src/MatchModes/OnOrBeforeMatchMode.php` (NEW)
- `src/MatchModes/OnOrAfterMatchMode.php` (NEW)
- `src/MatchModes/OlderThanMatchMode.php` (NEW)
- `src/MatchModes/NewerThanMatchMode.php` (NEW)
- `src/MatchModes/InLastMatchMode.php` (NEW)
- `src/MatchModes/PeriodMatchMode.php` (NEW)
- `src/Enums/FilterTypeEnum.php` (UPDATE)

## Notes

- Uses `date_format:Y-m-d` validation (ISO 8601 date format)
- Sanitizes to string format for DB comparison
- Optional `typedValue()` returns Carbon instances for type safety
- Works with MySQL DATE columns
- Carbon is already a dependency via Laravel
- Relative match modes use Carbon for date calculations
- Period mode supports: today, yesterday, tomorrow, this/last/next week/month/year
- Relative expressions make filters more user-friendly ("last 30 days" vs calculating exact dates)
