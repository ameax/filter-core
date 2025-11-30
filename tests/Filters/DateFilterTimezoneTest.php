<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\Filters\DateFilter;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Ameax\FilterCore\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

// Test filter for DATE columns (no timezone conversion)
class DateOnlyFilter extends DateFilter
{
    public function column(): string
    {
        return 'birth_date';
    }

    public function hasTime(): bool
    {
        return false;
    }
}

// Test filter for DATETIME columns (with timezone conversion)
class DateTimeColumnFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function hasTime(): bool
    {
        return true;
    }
}

class DateFilterTimezoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set a fixed "now" for consistent testing
        Carbon::setTestNow(Carbon::create(2024, 11, 15, 12, 0, 0, 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // =========================================================================
    // DATE-ONLY FILTER (no timezone conversion)
    // =========================================================================

    public function test_date_only_filter_has_time_returns_false(): void
    {
        $filter = new DateOnlyFilter;

        $this->assertFalse($filter->hasTime());
    }

    public function test_date_only_filter_uses_simple_boundaries(): void
    {
        config(['filter-core.timezone' => 'Europe/Berlin']);

        $filter = new DateOnlyFilter;
        $range = DateRangeValue::today();
        $mode = new DateRangeMatchMode;

        // Create mock query to capture the where clause
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                // For date-only, should use simple 00:00:00 to 23:59:59
                return $column === 'birth_date'
                    && str_starts_with($values[0], '2024-11-15 00:00:00')
                    && str_starts_with($values[1], '2024-11-15 23:59:59');
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    // =========================================================================
    // DATETIME FILTER (with timezone conversion)
    // =========================================================================

    public function test_datetime_filter_has_time_returns_true(): void
    {
        $filter = new DateTimeColumnFilter;

        $this->assertTrue($filter->hasTime());
    }

    public function test_datetime_filter_converts_berlin_to_utc(): void
    {
        // Europe/Berlin is UTC+1 in winter (November)
        config(['filter-core.timezone' => 'Europe/Berlin']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::today();
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                // "Today" in Berlin (2024-11-15 00:00:00 to 23:59:59 Berlin)
                // Should become UTC (2024-11-14 23:00:00 to 2024-11-15 22:59:59)
                return $column === 'created_at'
                    && $values[0] === '2024-11-14 23:00:00'
                    && $values[1] === '2024-11-15 22:59:59';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    public function test_datetime_filter_converts_new_york_to_utc(): void
    {
        // America/New_York is UTC-5 in November (EST)
        config(['filter-core.timezone' => 'America/New_York']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::today();
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                // "Today" in New York (2024-11-15 00:00:00 to 23:59:59 EST)
                // Should become UTC (2024-11-15 05:00:00 to 2024-11-16 04:59:59)
                return $column === 'created_at'
                    && $values[0] === '2024-11-15 05:00:00'
                    && $values[1] === '2024-11-16 04:59:59';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    public function test_datetime_filter_converts_tokyo_to_utc(): void
    {
        // Asia/Tokyo is UTC+9
        config(['filter-core.timezone' => 'Asia/Tokyo']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::today();
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                // "Today" in Tokyo (2024-11-15 00:00:00 to 23:59:59 JST)
                // Should become UTC (2024-11-14 15:00:00 to 2024-11-15 14:59:59)
                return $column === 'created_at'
                    && $values[0] === '2024-11-14 15:00:00'
                    && $values[1] === '2024-11-15 14:59:59';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    public function test_datetime_filter_uses_utc_when_no_timezone_configured(): void
    {
        // When timezone is null, should use app.timezone (UTC by default in tests)
        config(['filter-core.timezone' => null]);
        config(['app.timezone' => 'UTC']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::today();
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                // No conversion needed for UTC
                return $column === 'created_at'
                    && str_starts_with($values[0], '2024-11-15 00:00:00')
                    && str_starts_with($values[1], '2024-11-15 23:59:59');
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    // =========================================================================
    // NOT IN DATE RANGE
    // =========================================================================

    public function test_datetime_filter_not_in_range_with_timezone(): void
    {
        config(['filter-core.timezone' => 'Europe/Berlin']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::today();
        $mode = new NotInDateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereNotBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                return $column === 'created_at'
                    && $values[0] === '2024-11-14 23:00:00'
                    && $values[1] === '2024-11-15 22:59:59';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    // =========================================================================
    // OPEN-ENDED RANGES
    // =========================================================================

    public function test_datetime_filter_from_date_with_timezone(): void
    {
        config(['filter-core.timezone' => 'Europe/Berlin']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::from('2024-06-01');
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->withArgs(function ($column, $operator, $value) {
                // 2024-06-01 00:00:00 Berlin = 2024-05-31 22:00:00 UTC (summer time UTC+2)
                return $column === 'created_at'
                    && $operator === '>='
                    && $value === '2024-05-31 22:00:00';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    public function test_datetime_filter_until_date_with_timezone(): void
    {
        config(['filter-core.timezone' => 'Europe/Berlin']);

        $filter = new DateTimeColumnFilter;
        $range = DateRangeValue::until('2024-12-31');
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->withArgs(function ($column, $operator, $value) {
                // 2024-12-31 23:59:59 Berlin = 2024-12-31 22:59:59 UTC (winter time UTC+1)
                return $column === 'created_at'
                    && $operator === '<='
                    && $value === '2024-12-31 22:59:59';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    // =========================================================================
    // DYNAMIC FILTER
    // =========================================================================

    public function test_dynamic_date_filter_with_time(): void
    {
        $filter = DateFilter::dynamic('created_at')
            ->withColumn('created_at')
            ->withTime();

        $this->assertTrue($filter->hasTime());
    }

    public function test_dynamic_date_filter_without_time(): void
    {
        $filter = DateFilter::dynamic('birth_date')
            ->withColumn('birth_date');

        $this->assertFalse($filter->hasTime());
    }

    public function test_dynamic_datetime_filter_applies_timezone(): void
    {
        config(['filter-core.timezone' => 'Europe/Berlin']);

        $filter = DateFilter::dynamic('created_at')
            ->withColumn('created_at')
            ->withTime();

        $range = DateRangeValue::today();
        $mode = new DateRangeMatchMode;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereBetween')
            ->once()
            ->withArgs(function ($column, $values) {
                return $column === 'created_at'
                    && $values[0] === '2024-11-14 23:00:00'
                    && $values[1] === '2024-11-15 22:59:59';
            });

        $result = $filter->apply($query, $mode, $range);

        $this->assertTrue($result);
    }

    // =========================================================================
    // META
    // =========================================================================

    public function test_meta_includes_has_time(): void
    {
        $dateOnlyFilter = new DateOnlyFilter;
        $dateTimeFilter = new DateTimeColumnFilter;

        $this->assertFalse($dateOnlyFilter->meta()['hasTime']);
        $this->assertTrue($dateTimeFilter->meta()['hasTime']);
    }

    public function test_dynamic_filter_meta_includes_has_time(): void
    {
        $filter = DateFilter::dynamic('created_at')
            ->withColumn('created_at')
            ->withTime();

        $this->assertTrue($filter->meta()['hasTime']);
    }
}
