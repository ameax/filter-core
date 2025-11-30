# Date Filter

Filter date and datetime columns with extensive support for quick selections, relative ranges, specific periods, and custom date ranges. Includes timezone handling for DATETIME/TIMESTAMP columns.

## Overview

DateFilter supports six types of date range definitions:

| Type | Purpose | Example |
|------|---------|---------|
| Quick | Predefined ranges | Today, This Week, Last Month |
| Relative | Rolling ranges | Last 30 days, Next 2 weeks |
| Specific | Named periods | January 2024, Q4 Last Year |
| Annual Range | Cross-year periods | Fiscal Year, Academic Year |
| Custom | User-defined dates | 2024-01-01 to 2024-06-30 |
| Expression | Natural language | "first day of last month" |

## Creating a DateFilter

### Class-Based Definition

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\Filters\DateFilter;

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
}
```

### Restricting to Past or Future

```php
// Filter for birth dates (only past dates make sense)
class BirthDateFilter extends DateFilter
{
    public function column(): string
    {
        return 'birth_date';
    }

    public function allowedDirections(): ?array
    {
        return [DateDirection::PAST];
    }
}

// Filter for due dates (only future dates)
class DueDateFilter extends DateFilter
{
    public function column(): string
    {
        return 'due_at';
    }

    public function allowedDirections(): ?array
    {
        return [DateDirection::FUTURE];
    }

    // Include "today" option even for future-only filters
    public function allowToday(): bool
    {
        return true;
    }
}
```

### Timezone Handling for DATETIME Columns

When filtering DATETIME/TIMESTAMP columns stored in UTC, use `hasTime()` to enable timezone conversion:

```php
// Filter for DATETIME columns (stored in UTC)
class CreatedAtFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function hasTime(): bool
    {
        return true; // Enables timezone conversion
    }
}
```

**How it works:**

When `hasTime()` returns `true`, date ranges are converted from the configured timezone to UTC:

| User Selection | User Timezone | Database Query (UTC) |
|---------------|---------------|---------------------|
| "Today" (Nov 15) | Europe/Berlin (UTC+1) | `2024-11-14 23:00:00` to `2024-11-15 22:59:59` |
| "Today" (Nov 15) | America/New_York (UTC-5) | `2024-11-15 05:00:00` to `2024-11-16 04:59:59` |
| "Today" (Nov 15) | Asia/Tokyo (UTC+9) | `2024-11-14 15:00:00` to `2024-11-15 14:59:59` |

**Configuration:**

Set the user timezone in `config/filter-core.php`:

```php
return [
    'timezone' => 'Europe/Berlin', // User's timezone for queries
];
```

Or leave as `null` to use `config('app.timezone')`.

### Dynamic Filter

```php
use Ameax\FilterCore\Filters\DateFilter;

// Basic date filter (DATE column)
$filter = DateFilter::dynamic('birth_date')
    ->withColumn('birth_date')
    ->withLabel('Birth Date');

// DATETIME column with timezone handling
$filter = DateFilter::dynamic('created_at')
    ->withColumn('created_at')
    ->withLabel('Created Date')
    ->withTime(); // Enables timezone conversion

// Past-only filter
$filter = DateFilter::dynamic('birth_date')
    ->withColumn('birth_date')
    ->withLabel('Birth Date')
    ->withPastOnly();

// Future-only filter with today allowed
$filter = DateFilter::dynamic('due_date')
    ->withColumn('due_at')
    ->withLabel('Due Date')
    ->withFutureOnly()
    ->withAllowToday(true)
    ->withTime(); // DATETIME column

// Nullable date filter
$filter = DateFilter::dynamic('deleted_at')
    ->withColumn('deleted_at')
    ->withNullable()
    ->withTime();

// With relation
$filter = DateFilter::dynamic('company_created')
    ->withColumn('created_at')
    ->withRelation('company')
    ->withTime();
```

## Using DateRangeValue

`DateRangeValue` is the universal DTO for all date range definitions. It provides factory methods for every range type.

### Quick Selections

```php
use Ameax\FilterCore\DateRange\DateRangeValue;

// Day-based
DateRangeValue::today();
DateRangeValue::yesterday();
DateRangeValue::tomorrow();

// Week-based
DateRangeValue::thisWeek();
DateRangeValue::lastWeek();

// Month-based
DateRangeValue::thisMonth();
DateRangeValue::lastMonth();

// Quarter-based
DateRangeValue::thisQuarter();
DateRangeValue::lastQuarter();

// Year-based
DateRangeValue::thisYear();
DateRangeValue::lastYear();
```

### Relative Ranges

```php
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\DateUnit;

// Last X units (rolling window including today)
DateRangeValue::lastDays(30);           // Last 30 days
DateRangeValue::lastWeeks(2);           // Last 2 weeks
DateRangeValue::lastMonths(3);          // Last 3 months
DateRangeValue::lastQuarters(2);        // Last 2 quarters
DateRangeValue::lastYears(2);           // Last 2 years

// Exclude current partial unit
DateRangeValue::lastDays(30, includeToday: false);
DateRangeValue::lastMonths(3, includeCurrentMonth: false);

// Next X units (future window)
DateRangeValue::nextDays(7);            // Next 7 days
DateRangeValue::nextWeeks(2);           // Next 2 weeks
DateRangeValue::nextMonths(3);          // Next 3 months

// Age-based filtering
DateRangeValue::olderThan(90, DateUnit::DAY);   // More than 90 days old
DateRangeValue::newerThan(30, DateUnit::DAY);   // Within last 30 days
DateRangeValue::olderThan(2, DateUnit::YEAR);   // More than 2 years old
```

### Specific Periods

```php
use Ameax\FilterCore\DateRange\DateRangeValue;

// Specific month
DateRangeValue::month(3);                  // March this year
DateRangeValue::month(6, yearOffset: -1);  // June last year

// Specific quarter
DateRangeValue::quarter(2);                // Q2 this year
DateRangeValue::quarter(4, yearOffset: -1); // Q4 last year

// Specific half-year (H1 = Jan-Jun, H2 = Jul-Dec)
DateRangeValue::halfYear(1);               // H1 this year (Jan-Jun)
DateRangeValue::halfYear(2);               // H2 this year (Jul-Dec)
DateRangeValue::halfYear(1, yearOffset: -1); // H1 last year

// Specific week (ISO week number)
DateRangeValue::week(1);                   // Week 1 this year
DateRangeValue::week(52, yearOffset: -1);  // Week 52 last year

// Specific year
DateRangeValue::year(2023);                // Full year 2023

// X units ago (a single specific period)
DateRangeValue::unitAgo(2, DateUnit::MONTH); // The month 2 months ago
```

### Annual Ranges

For cross-year periods like fiscal years or academic years:

```php
use Ameax\FilterCore\DateRange\DateRangeValue;

// Fiscal year starting in July
DateRangeValue::fiscalYear(startMonth: 7);           // Current fiscal year
DateRangeValue::fiscalYear(startMonth: 7, yearOffset: -1); // Last fiscal year

// Academic year starting in September
DateRangeValue::academicYear(startMonth: 9);         // Current academic year

// Custom annual range
DateRangeValue::annualRange(startMonth: 4);          // April to March
```

### Custom Ranges

```php
use Ameax\FilterCore\DateRange\DateRangeValue;

// Closed range (both dates specified)
DateRangeValue::between('2024-01-01', '2024-06-30');

// Open-end range (from date onwards)
DateRangeValue::from('2024-06-01');  // June 1st onwards (no end)

// Open-start range (up to date)
DateRangeValue::until('2024-12-31'); // Everything up to Dec 31
```

### Expression Syntax

For power users, natural language expressions:

```php
use Ameax\FilterCore\DateRange\DateRangeValue;

// Single date expression (becomes single-day range)
DateRangeValue::expression('first day of last month');
DateRangeValue::expression('next monday');
DateRangeValue::expression('-3 weeks');

// Range expression (start and end)
DateRangeValue::rangeExpression(
    'first day of last month',
    'last day of last month'
);
```

## Applying Date Filters

### Basic Usage

```php
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;

$filter = CreatedAtFilter::make();

// Filter this month's records
$result = QueryApplicator::for(User::query())
    ->withFilters([$filter])
    ->applyFilter(new FilterValue(
        'CreatedAtFilter',
        new DateRangeMatchMode(),
        DateRangeValue::thisMonth()
    ))
    ->getQuery()
    ->get();
```

### Match Modes

DateFilter supports two match modes:

| Mode | Description | SQL |
|------|-------------|-----|
| `DateRangeMatchMode` | Records within range | `WHERE col BETWEEN start AND end` |
| `NotInDateRangeMatchMode` | Records outside range | `WHERE col NOT BETWEEN start AND end` |

```php
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;

// Within date range
new FilterValue('CreatedAtFilter', new DateRangeMatchMode(), $range);

// NOT within date range
new FilterValue('CreatedAtFilter', new NotInDateRangeMatchMode(), $range);
```

### Open-Ended Ranges

```php
// Older than 90 days (open start)
// SQL: WHERE created_at <= '2024-08-16 23:59:59'
$range = DateRangeValue::olderThan(90, DateUnit::DAY);

// From June 1st onwards (open end)
// SQL: WHERE created_at >= '2024-06-01 00:00:00'
$range = DateRangeValue::from('2024-06-01');
```

## Resolving Date Ranges

`DateRangeValue` can be resolved to concrete start/end dates:

```php
use Carbon\Carbon;

$range = DateRangeValue::thisMonth();
$resolved = $range->resolve();

echo $resolved->start; // 2024-11-01 00:00:00
echo $resolved->end;   // 2024-11-30 23:59:59

// ResolvedDateRange provides helper methods
$resolved->isClosed();      // true (both start and end)
$resolved->isOpenStart();   // false
$resolved->isOpenEnd();     // false
$resolved->durationInDays(); // 29

// Check if a date is within the range
$resolved->contains(Carbon::parse('2024-11-15')); // true
```

## Quick Date Range Options

For building UIs, DateFilter provides helper methods:

```php
$filter = BirthDateFilter::make();

// Get simple key => label map
$options = $filter->getQuickOptions();
// ['today' => 'Today', 'yesterday' => 'Yesterday', ...]

// Get grouped options
$grouped = $filter->getGroupedQuickOptions();
// [
//     'day' => ['today' => 'Today', 'yesterday' => 'Yesterday'],
//     'week' => ['this_week' => 'This Week', 'last_week' => 'Last Week'],
//     'month' => [...],
//     'quarter' => [...],
//     'half_year' => [...],
//     'year' => [...],
//     'rolling' => ['last_7_days' => 'Last 7 Days', ...],
// ]
```

When using direction restrictions, only appropriate options are returned:

```php
// Past-only filter excludes "Tomorrow", "Next Week", etc.
$pastFilter = BirthDateFilter::make();
$options = $pastFilter->getQuickOptions(); // Only past options

// Future-only filter excludes "Yesterday", "Last Week", etc.
$futureFilter = DueDateFilter::make();
$options = $futureFilter->getQuickOptions(); // Only future options
```

## Available Quick Ranges

The `QuickDateRange` enum provides 36 predefined options:

### Day
- `TODAY` - Today
- `YESTERDAY` - Yesterday
- `TOMORROW` - Tomorrow (future)

### Week
- `THIS_WEEK` - This Week
- `LAST_WEEK` - Last Week
- `NEXT_WEEK` - Next Week (future)

### Month
- `THIS_MONTH` - This Month
- `LAST_MONTH` - Last Month
- `NEXT_MONTH` - Next Month (future)

### Quarter
- `THIS_QUARTER` - This Quarter
- `LAST_QUARTER` - Last Quarter
- `NEXT_QUARTER` - Next Quarter (future)
- `Q1_THIS_YEAR` - Q1 This Year
- `Q2_THIS_YEAR` - Q2 This Year
- `Q3_THIS_YEAR` - Q3 This Year
- `Q4_THIS_YEAR` - Q4 This Year
- `Q1_LAST_YEAR` - Q1 Last Year
- `Q2_LAST_YEAR` - Q2 Last Year
- `Q3_LAST_YEAR` - Q3 Last Year
- `Q4_LAST_YEAR` - Q4 Last Year

### Half Year
- `THIS_HALF_YEAR` - This Half Year
- `LAST_HALF_YEAR` - Last Half Year
- `NEXT_HALF_YEAR` - Next Half Year (future)
- `H1_THIS_YEAR` - H1 This Year (Jan-Jun)
- `H2_THIS_YEAR` - H2 This Year (Jul-Dec)
- `H1_LAST_YEAR` - H1 Last Year
- `H2_LAST_YEAR` - H2 Last Year

### Year
- `THIS_YEAR` - This Year
- `LAST_YEAR` - Last Year
- `NEXT_YEAR` - Next Year (future)

### Rolling Periods
- `LAST_7_DAYS` - Last 7 Days
- `LAST_30_DAYS` - Last 30 Days
- `LAST_90_DAYS` - Last 90 Days
- `NEXT_7_DAYS` - Next 7 Days (future)
- `NEXT_30_DAYS` - Next 30 Days (future)

## Serialization

DateRangeValue supports JSON serialization for storing filter state:

```php
// Serialize to array
$range = DateRangeValue::lastDays(30);
$array = $range->toArray();
// ['type' => 'relative', 'direction' => 'past', 'amount' => 30, 'unit' => 'day', ...]

// Store as JSON
$json = json_encode($array);

// Restore from array
$restored = DateRangeValue::fromArray(json_decode($json, true));
```

## Meta Information

DateFilter includes useful metadata for UI construction:

```php
$filter = BirthDateFilter::make();
$definition = $filter->toDefinition();

$meta = $definition->getMeta();
// [
//     'allowedDirections' => ['past'],
//     'allowToday' => true,
//     'quickOptions' => ['today' => 'Today', ...]
// ]
```

## Generated SQL Examples

| Range Type | SQL |
|------------|-----|
| `thisMonth()` | `WHERE col BETWEEN '2024-11-01 00:00:00' AND '2024-11-30 23:59:59'` |
| `lastDays(30)` | `WHERE col BETWEEN '2024-10-17 00:00:00' AND '2024-11-15 23:59:59'` |
| `olderThan(90, DAY)` | `WHERE col <= '2024-08-16 23:59:59'` |
| `newerThan(30, DAY)` | `WHERE col BETWEEN '2024-10-16 00:00:00' AND '2024-11-15 23:59:59'` |
| `from('2024-06-01')` | `WHERE col >= '2024-06-01 00:00:00'` |
| `until('2024-12-31')` | `WHERE col <= '2024-12-31 23:59:59'` |
| NotInDateRange | `WHERE col NOT BETWEEN start AND end` |

## Quick Filter Presets (Database-Driven)

For applications that need user-configurable quick presets, use the `QuickFilterPreset` model:

```php
use Ameax\FilterCore\Models\QuickFilterPreset;
use Ameax\FilterCore\DateRange\DateDirection;

// Get presets for a filter
$presets = QuickFilterPreset::getForFilter(
    scopes: ['invoices'],           // Optional scope filtering
    allowedDirections: [DateDirection::PAST]  // Optional direction filtering
);

// Get as options array for API response
$options = QuickFilterPreset::getOptionsForFilter();
// [
//     ['id' => 1, 'label' => 'Today', 'config' => ['type' => 'quick', 'quick' => 'today']],
//     ['id' => 2, 'label' => 'Last 30 Days', 'config' => ['type' => 'relative', ...]],
// ]
```

### Dual Storage Pattern

When persisting filters (e.g., dashboard widgets), store both the preset ID and the config:

```json
{
    "filter": "CreatedAtFilter",
    "mode": "dateRange",
    "filter_quick_preset_id": 42,
    "value": {"type": "relative", "direction": "past", "amount": 30, "unit": "day"}
}
```

This ensures:
- **Preset exists**: UI shows the preset as selected
- **Preset deleted**: Filter still works using the stored config
- **Preset modified**: Saved filter remains unchanged (intentional)

### Auto-Generated Labels

Labels are automatically generated from the config via `DateRangeValue::toLabel()`:

```php
$range = DateRangeValue::lastDays(30);
echo $range->toLabel(); // "Last 30 Days" or "Letzte 30 Tage" (localized)
```

### Seeding Default Presets

The package includes a seeder with ~100 common presets:

```bash
php artisan db:seed --class="Ameax\\FilterCore\\Database\\Seeders\\QuickFilterPresetSeeder"
```

Presets have an `is_active` flag - only common ones are active by default.

## Next Steps

- [Dynamic Filters](./07-dynamic-filters.md) - More dynamic filter options
- [Match Modes](./03-match-modes.md) - All match mode types
- [Filter Types](./02-filter-types.md) - Other filter types
