# TODO: DateTimeFilter Type

**Priority:** High
**Status:** Open

## Problem

No built-in filter type for datetime/timestamp columns (`DATETIME`, `TIMESTAMP`). Users must create custom implementations.

## Proposed Solution

Add `DateTimeFilter` base class for filtering datetime columns with timezone support.

### Implementation

```php
namespace Ameax\FilterCore\Filters;

abstract class DateTimeFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATETIME;
    }

    public function allowedMatchModes(): array
    {
        return [
            new IsMatchMode(),
            new IsNotMatchMode(),
            new BetweenMatchMode(),
            new BeforeMatchMode(),
            new AfterMatchMode(),
            new OnOrBeforeMatchMode(),
            new OnOrAfterMatchMode(),
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
            return $value->toDateTimeString(); // 'Y-m-d H:i:s'
        }

        if (is_string($value)) {
            try {
                return \Carbon\Carbon::parse($value)->toDateTimeString();
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
                'value.*' => ['date'],
            ];
        }

        if ($mode instanceof EmptyMatchMode || $mode instanceof NotEmptyMatchMode) {
            return [];
        }

        return [
            'value' => ['required', 'date'],
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

### Relative DateTime Match Modes

Same as DateFilter but with time components:

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
        // $value = ['amount' => 30, 'unit' => 'days'] or ['amount' => 2, 'unit' => 'hours']
        $datetime = Carbon::now()->sub($value['amount'], $value['unit']);
        $query->where($column, '<', $datetime->toDateTimeString());
    }
}

// NewerThanMatchMode, InLastMatchMode, PeriodMatchMode similar to DateFilter
// but using toDateTimeString() instead of toDateString()
```

### Usage Example

```php
class PublishedAtFilter extends DateTimeFilter
{
    public function column(): string
    {
        return 'published_at';
    }

    public function label(): string
    {
        return 'Published Date & Time';
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

// Absolute datetimes
$posts = Post::query()
    ->applyFilters([
        // Exact datetime
        FilterValue::for(PublishedAtFilter::class)->is('2024-01-15 14:30:00'),

        // Range
        FilterValue::for(PublishedAtFilter::class)
            ->between('2024-01-01 00:00:00', '2024-12-31 23:59:59'),

        // After specific datetime
        FilterValue::for(PublishedAtFilter::class)->after('2024-06-01 12:00:00'),

        // On or before
        FilterValue::for(PublishedAtFilter::class)->onOrBefore('2024-12-31 23:59:59'),
    ])
    ->get();

// Relative datetimes
$posts = Post::query()
    ->applyFilters([
        // Older than 2 hours
        FilterValue::for(PublishedAtFilter::class)
            ->olderThan(['amount' => 2, 'unit' => 'hours']),

        // In the last 30 minutes
        FilterValue::for(PublishedAtFilter::class)
            ->inLast(['amount' => 30, 'unit' => 'minutes']),

        // Published today
        FilterValue::for(PublishedAtFilter::class)
            ->period('today'),

        // Last week
        FilterValue::for(PublishedAtFilter::class)
            ->period('last_week'),
    ])
    ->get();
```

### Timezone Handling

```php
// User input with timezone
$value = '2024-01-15 14:30:00 America/New_York';
$carbon = Carbon::parse($value); // Parses with timezone

// Or explicit timezone
$carbon = Carbon::parse('2024-01-15 14:30:00', 'America/New_York');

// Converts to app timezone for DB storage
$dbValue = $carbon->toDateTimeString(); // Uses app timezone
```

### Dynamic Filter Support

```php
$filter = DateTimeFilter::dynamic('published_at')
    ->withColumn('published_at')
    ->withLabel('Published At');

$selection = FilterSelection::make()
    ->where($filter)->after('2024-01-01 00:00:00');
```

### Date-Only Queries on DateTime Columns

```php
// Filter by date only (ignores time)
class PublishedDateFilter extends DateTimeFilter
{
    public function apply(
        Builder|QueryBuilder $query,
        MatchModeContract $mode,
        mixed $value
    ): bool {
        $column = $this->column();

        if ($mode instanceof IsMatchMode) {
            // Match entire day
            $date = Carbon::parse($value)->startOfDay();
            $query->whereBetween($column, [
                $date->toDateTimeString(),
                $date->copy()->endOfDay()->toDateTimeString(),
            ]);
            return true;
        }

        // Use default logic for other modes
        return false;
    }
}
```

## Differences from DateFilter

| Feature | DateFilter | DateTimeFilter |
|---------|-----------|----------------|
| DB Column | `DATE` | `DATETIME`, `TIMESTAMP` |
| Format | `Y-m-d` | `Y-m-d H:i:s` |
| Validation | `date_format:Y-m-d` | `date` |
| Time Component | ❌ No | ✅ Yes |
| Timezone Support | ❌ N/A | ✅ Yes (via Carbon) |

## Implementation Steps

1. Add `FilterTypeEnum::DATETIME` case
2. Create `DateTimeFilter` base class (reuses match modes from DateFilter)
3. Add support for relative datetime modes (hours, minutes, seconds in addition to days/months/years)
4. Add `DynamicDateTimeFilter` class
5. Add comprehensive tests:
   - DateTime sanitization (string, Carbon with timezone, null)
   - All absolute match modes with time components
   - All relative match modes (hours, minutes, seconds)
   - Between with datetime range
   - Period resolution with time boundaries
   - Timezone handling
   - Validation rules
   - Date-only queries on datetime columns
   - Dynamic filter
6. Add to documentation

## Related Files

- `src/Filters/DateTimeFilter.php` (NEW)
- `src/Filters/Dynamic/DynamicDateTimeFilter.php` (NEW)
- `src/Enums/FilterTypeEnum.php` (UPDATE)
- Match modes reused from DateFilter (OlderThan, NewerThan, InLast, Period)
  - But adapted to use toDateTimeString() instead of toDateString()

## Notes

- Uses Laravel's `date` validation rule (flexible date parsing)
- Sanitizes to `Y-m-d H:i:s` format for DB comparison
- Carbon handles timezone conversion automatically
- Works with MySQL DATETIME and TIMESTAMP columns
- Can customize via `apply()` for special cases (date-only on datetime column)
- More flexible than DateFilter due to time component
- Relative modes support hours, minutes, seconds in addition to days/months/years
- Period mode uses start/end of day for datetime columns (00:00:00 to 23:59:59)
