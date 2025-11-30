<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\ResolvedDateRange;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\DateFilter;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;
use Carbon\Carbon;

// Test filter that only allows past dates
class KoiBirthDateFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function label(): string
    {
        return 'Birth Date';
    }

    public function allowedDirections(): ?array
    {
        return [DateDirection::PAST];
    }
}

// Test filter that allows all directions
class KoiRegistrationDateFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function label(): string
    {
        return 'Registration Date';
    }
}

class DateFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 11, 15, 12, 0, 0));

        $pond = Pond::create(['name' => 'Test Pond', 'water_type' => 'fresh', 'capacity' => 5000, 'is_heated' => true]);

        // Create kois with different dates (manually set created_at via save)
        $veryOld = new Koi([
            'name' => 'Very Old Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 5.00,
            'price_cents' => 9999,
        ]);
        $veryOld->created_at = Carbon::create(2023, 1, 15); // Last year
        $veryOld->save();

        $old = new Koi([
            'name' => 'Old Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 4.00,
            'price_cents' => 7999,
        ]);
        $old->created_at = Carbon::create(2024, 6, 15); // H1 this year
        $old->save();

        $recent = new Koi([
            'name' => 'Recent Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 2.00,
            'price_cents' => 4999,
        ]);
        $recent->created_at = Carbon::create(2024, 10, 15); // October
        $recent->save();

        $new = new Koi([
            'name' => 'New Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 1.00,
            'price_cents' => 2999,
        ]);
        $new->created_at = Carbon::create(2024, 11, 10); // This month
        $new->save();

        $today = new Koi([
            'name' => 'Today Koi',
            'status' => 'pending',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 0.50,
            'price_cents' => 1999,
        ]);
        $today->created_at = Carbon::create(2024, 11, 15); // Today
        $today->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ========================================
    // Filter Class Tests
    // ========================================

    public function test_date_filter_returns_correct_type(): void
    {
        $filter = KoiBirthDateFilter::make();

        $this->assertEquals(FilterTypeEnum::DATE, $filter->type());
        $this->assertEquals('created_at', $filter->column());
        $this->assertEquals('Birth Date', $filter->label());
    }

    public function test_date_filter_returns_allowed_modes(): void
    {
        $filter = KoiBirthDateFilter::make();
        $modes = $filter->allowedModes();

        $this->assertCount(2, $modes);
        $this->assertInstanceOf(DateRangeMatchMode::class, $modes[0]);
        $this->assertInstanceOf(NotInDateRangeMatchMode::class, $modes[1]);
    }

    public function test_date_filter_past_only_directions(): void
    {
        $filter = KoiBirthDateFilter::make();

        $this->assertEquals([DateDirection::PAST], $filter->allowedDirections());
    }

    public function test_date_filter_all_directions(): void
    {
        $filter = KoiRegistrationDateFilter::make();

        $this->assertNull($filter->allowedDirections());
    }

    public function test_date_filter_converts_to_definition(): void
    {
        $filter = KoiBirthDateFilter::make();
        $definition = $filter->toDefinition();

        $this->assertEquals('KoiBirthDateFilter', $definition->getKey());
        $this->assertEquals(FilterTypeEnum::DATE, $definition->getType());
        $this->assertEquals('created_at', $definition->getColumn());
        $this->assertEquals('Birth Date', $definition->getLabel());
    }

    // ========================================
    // Query Application Tests
    // ========================================

    public function test_filter_this_month(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::thisMonth();

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // New Koi (Nov 10) and Today Koi (Nov 15) are in November
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains('name', 'New Koi'));
        $this->assertTrue($result->contains('name', 'Today Koi'));
    }

    public function test_filter_this_year(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::thisYear();

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // All 2024 kois (Old Koi, Recent Koi, New Koi, Today Koi)
        $this->assertCount(4, $result);
        $this->assertFalse($result->contains('name', 'Very Old Koi')); // 2023
    }

    public function test_filter_last_year(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::lastYear();

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // Only Very Old Koi is from 2023
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains('name', 'Very Old Koi'));
    }

    public function test_filter_h1_this_year(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::halfYear(1, yearOffset: 0);

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // Old Koi is from June (H1)
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains('name', 'Old Koi'));
    }

    public function test_filter_custom_date_range(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::between('2024-06-01', '2024-10-31');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // Old Koi (June) and Recent Koi (October)
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains('name', 'Old Koi'));
        $this->assertTrue($result->contains('name', 'Recent Koi'));
    }

    public function test_filter_not_in_date_range(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::thisYear();

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new NotInDateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // Only Very Old Koi is NOT in 2024
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains('name', 'Very Old Koi'));
    }

    public function test_filter_older_than(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::olderThan(60, \Ameax\FilterCore\DateRange\DateUnit::DAY);

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // Very Old Koi and Old Koi are older than 60 days
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains('name', 'Very Old Koi'));
        $this->assertTrue($result->contains('name', 'Old Koi'));
    }

    public function test_filter_newer_than(): void
    {
        $filter = KoiBirthDateFilter::make();
        $range = DateRangeValue::newerThan(30, \Ameax\FilterCore\DateRange\DateUnit::DAY);

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('KoiBirthDateFilter', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        // Oct 15 is 31 days before Nov 15 (not within 30 days)
        // New Koi (Nov 10), Today Koi (Nov 15) are newer than 30 days
        $this->assertCount(2, $result);
        $this->assertFalse($result->contains('name', 'Recent Koi')); // 31 days ago
        $this->assertTrue($result->contains('name', 'New Koi'));
        $this->assertTrue($result->contains('name', 'Today Koi'));
    }

    // ========================================
    // Dynamic Filter Tests
    // ========================================

    public function test_dynamic_date_filter_basic(): void
    {
        $filter = DateFilter::dynamic('birth_date')
            ->withColumn('created_at')
            ->withLabel('Birth Date');

        $this->assertEquals('birth_date', $filter->getKey());
        $this->assertEquals('created_at', $filter->column());
        $this->assertEquals('Birth Date', $filter->label());
        $this->assertEquals(FilterTypeEnum::DATE, $filter->type());
    }

    public function test_dynamic_date_filter_past_only(): void
    {
        $filter = DateFilter::dynamic('birth_date')
            ->withColumn('created_at')
            ->withPastOnly();

        $this->assertEquals([DateDirection::PAST], $filter->allowedDirections());
    }

    public function test_dynamic_date_filter_future_only(): void
    {
        $filter = DateFilter::dynamic('due_date')
            ->withColumn('due_at')
            ->withFutureOnly();

        $this->assertEquals([DateDirection::FUTURE], $filter->allowedDirections());
    }

    public function test_dynamic_date_filter_in_query(): void
    {
        $filter = DateFilter::dynamic('birth_date')
            ->withColumn('created_at')
            ->withLabel('Birth Date');

        $range = DateRangeValue::thisMonth();

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('birth_date', new DateRangeMatchMode, $range))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
    }

    public function test_dynamic_date_filter_with_relation(): void
    {
        $filter = DateFilter::dynamic('pond_created')
            ->withColumn('created_at')
            ->withRelation('pond');

        $this->assertEquals('pond', $filter->getRelation());
    }

    public function test_dynamic_date_filter_nullable(): void
    {
        $filter = DateFilter::dynamic('deleted_at')
            ->withColumn('deleted_at')
            ->withNullable();

        $this->assertTrue($filter->nullable());
    }

    public function test_dynamic_date_filter_with_meta(): void
    {
        $filter = DateFilter::dynamic('birth_date')
            ->withColumn('created_at')
            ->withMeta(['icon' => 'calendar']);

        $meta = $filter->meta();
        $this->assertEquals('calendar', $meta['icon']);
    }

    public function test_dynamic_date_filter_allow_today(): void
    {
        $filter = DateFilter::dynamic('future_date')
            ->withColumn('due_at')
            ->withFutureOnly()
            ->withAllowToday(true);

        $this->assertTrue($filter->allowToday());
    }

    // ========================================
    // ResolvedDateRange Tests
    // ========================================

    public function test_resolved_date_range_closed(): void
    {
        $start = Carbon::create(2024, 1, 1);
        $end = Carbon::create(2024, 12, 31);
        $range = new ResolvedDateRange($start, $end);

        $this->assertTrue($range->isClosed());
        $this->assertFalse($range->isOpenStart());
        $this->assertFalse($range->isOpenEnd());
        $this->assertFalse($range->isUnbounded());
    }

    public function test_resolved_date_range_open_start(): void
    {
        $end = Carbon::create(2024, 12, 31);
        $range = new ResolvedDateRange(null, $end);

        $this->assertFalse($range->isClosed());
        $this->assertTrue($range->isOpenStart());
        $this->assertFalse($range->isOpenEnd());
        $this->assertFalse($range->isUnbounded());
    }

    public function test_resolved_date_range_open_end(): void
    {
        $start = Carbon::create(2024, 1, 1);
        $range = new ResolvedDateRange($start, null);

        $this->assertFalse($range->isClosed());
        $this->assertFalse($range->isOpenStart());
        $this->assertTrue($range->isOpenEnd());
        $this->assertFalse($range->isUnbounded());
    }

    public function test_resolved_date_range_contains(): void
    {
        $start = Carbon::create(2024, 1, 1);
        $end = Carbon::create(2024, 12, 31);
        $range = new ResolvedDateRange($start, $end);

        $this->assertTrue($range->contains(Carbon::create(2024, 6, 15)));
        $this->assertFalse($range->contains(Carbon::create(2023, 6, 15)));
        $this->assertFalse($range->contains(Carbon::create(2025, 6, 15)));
    }

    public function test_resolved_date_range_duration(): void
    {
        $start = Carbon::create(2024, 1, 1);
        $end = Carbon::create(2024, 1, 31);
        $range = new ResolvedDateRange($start, $end);

        $this->assertEquals(30, $range->durationInDays());
    }

    public function test_resolved_date_range_duration_null_for_open(): void
    {
        $range = new ResolvedDateRange(null, Carbon::create(2024, 12, 31));

        $this->assertNull($range->durationInDays());
    }
}
