<?php

namespace Ameax\FilterCore\Tests\DateRange;

use Ameax\FilterCore\DateRange\DateRangeType;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\DateUnit;
use Ameax\FilterCore\DateRange\QuickDateRange;
use Ameax\FilterCore\Tests\TestCase;
use Carbon\Carbon;
use InvalidArgumentException;

class DateRangeValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time for consistent tests
        Carbon::setTestNow(Carbon::create(2024, 11, 15, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ========================================
    // Quick Selections Tests
    // ========================================

    public function test_today_returns_today_range(): void
    {
        $range = DateRangeValue::today();
        $resolved = $range->resolve();

        $this->assertEquals('2024-11-15 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-15 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_yesterday_returns_yesterday_range(): void
    {
        $range = DateRangeValue::yesterday();
        $resolved = $range->resolve();

        $this->assertEquals('2024-11-14 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-14 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_this_week_returns_current_week(): void
    {
        $range = DateRangeValue::thisWeek();
        $resolved = $range->resolve();

        // Nov 15, 2024 is a Friday, so week starts Monday Nov 11
        $this->assertEquals('2024-11-11 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-17 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_this_month_returns_current_month(): void
    {
        $range = DateRangeValue::thisMonth();
        $resolved = $range->resolve();

        $this->assertEquals('2024-11-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_this_quarter_returns_current_quarter(): void
    {
        $range = DateRangeValue::thisQuarter();
        $resolved = $range->resolve();

        // November is Q4
        $this->assertEquals('2024-10-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-12-31 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_this_year_returns_current_year(): void
    {
        $range = DateRangeValue::thisYear();
        $resolved = $range->resolve();

        $this->assertEquals('2024-01-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-12-31 23:59:59', $resolved->end->toDateTimeString());
    }

    // ========================================
    // Relative Ranges Tests
    // ========================================

    public function test_last_30_days_includes_today(): void
    {
        $range = DateRangeValue::lastDays(30, includeToday: true);
        $resolved = $range->resolve();

        $this->assertEquals('2024-10-17 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-15 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_last_7_days_calculation(): void
    {
        $range = DateRangeValue::lastDays(7, includeToday: true);
        $resolved = $range->resolve();

        // 7 days including today: Nov 9 - Nov 15
        $this->assertEquals('2024-11-09 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-15 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_last_2_months_includes_current(): void
    {
        $range = DateRangeValue::lastMonths(2, includeCurrentMonth: true);
        $resolved = $range->resolve();

        // 2 months including current: Oct - Nov
        $this->assertEquals('2024-10-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-15 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_last_2_months_excludes_current(): void
    {
        $range = DateRangeValue::lastMonths(2, includeCurrentMonth: false);
        $resolved = $range->resolve();

        // 2 complete months before current: Sep - Oct
        $this->assertEquals('2024-09-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-10-31 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_next_7_days(): void
    {
        $range = DateRangeValue::nextDays(7, includeToday: true);
        $resolved = $range->resolve();

        // 7 days including today: Nov 15 - Nov 21
        $this->assertEquals('2024-11-15 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-21 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_older_than_90_days(): void
    {
        $range = DateRangeValue::olderThan(90, DateUnit::DAY);
        $resolved = $range->resolve();

        // Open start, end at one second before 90 days ago starts
        // Nov 15 - 90 days = Aug 17, boundary is Aug 16 23:59:59
        $this->assertNull($resolved->start);
        $this->assertEquals('2024-08-16', $resolved->end->toDateString());
    }

    public function test_newer_than_30_days(): void
    {
        $range = DateRangeValue::newerThan(30, DateUnit::DAY);
        $resolved = $range->resolve();

        // From 30 days ago to now
        $this->assertEquals('2024-10-16 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-15 23:59:59', $resolved->end->toDateTimeString());
    }

    // ========================================
    // Specific Periods Tests
    // ========================================

    public function test_unit_ago_returns_single_unit(): void
    {
        // 2 months ago = September 2024
        $range = DateRangeValue::unitAgo(2, DateUnit::MONTH);
        $resolved = $range->resolve();

        $this->assertEquals('2024-09-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-09-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_specific_month_current_year(): void
    {
        $range = DateRangeValue::month(3, yearOffset: 0); // March this year
        $resolved = $range->resolve();

        $this->assertEquals('2024-03-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-03-31 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_specific_month_last_year(): void
    {
        $range = DateRangeValue::month(6, yearOffset: -1); // June last year
        $resolved = $range->resolve();

        $this->assertEquals('2023-06-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2023-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_specific_quarter(): void
    {
        $range = DateRangeValue::quarter(2, yearOffset: 0); // Q2 this year
        $resolved = $range->resolve();

        $this->assertEquals('2024-04-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_half_year_h1(): void
    {
        $range = DateRangeValue::halfYear(1, yearOffset: 0); // H1 this year
        $resolved = $range->resolve();

        $this->assertEquals('2024-01-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_half_year_h2(): void
    {
        $range = DateRangeValue::halfYear(2, yearOffset: 0); // H2 this year
        $resolved = $range->resolve();

        $this->assertEquals('2024-07-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-12-31 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_half_year_last_year(): void
    {
        $range = DateRangeValue::halfYear(1, yearOffset: -1); // H1 last year
        $resolved = $range->resolve();

        $this->assertEquals('2023-01-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2023-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_specific_week(): void
    {
        $range = DateRangeValue::week(1, yearOffset: 0); // Week 1 of 2024
        $resolved = $range->resolve();

        // ISO week 1 of 2024 starts on Monday Jan 1, 2024
        $this->assertEquals('2024-01-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-01-07 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_specific_year(): void
    {
        $range = DateRangeValue::year(2023);
        $resolved = $range->resolve();

        $this->assertEquals('2023-01-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2023-12-31 23:59:59', $resolved->end->toDateTimeString());
    }

    // ========================================
    // Annual Ranges Tests
    // ========================================

    public function test_fiscal_year_starting_july(): void
    {
        $range = DateRangeValue::fiscalYear(startMonth: 7, yearOffset: 0);
        $resolved = $range->resolve();

        // Current date is Nov 2024, so we're in FY 2024 (Jul 2024 - Jun 2025)
        $this->assertEquals('2024-07-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2025-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_fiscal_year_last_year(): void
    {
        $range = DateRangeValue::fiscalYear(startMonth: 7, yearOffset: -1);
        $resolved = $range->resolve();

        // FY 2023 = Jul 2023 - Jun 2024
        $this->assertEquals('2023-07-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_academic_year(): void
    {
        $range = DateRangeValue::academicYear(startMonth: 9, yearOffset: 0);
        $resolved = $range->resolve();

        // Current date is Nov 2024, so we're in academic year 2024 (Sep 2024 - Aug 2025)
        $this->assertEquals('2024-09-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2025-08-31 23:59:59', $resolved->end->toDateTimeString());
    }

    // ========================================
    // Custom Ranges Tests
    // ========================================

    public function test_between_two_dates(): void
    {
        $range = DateRangeValue::between('2024-01-15', '2024-03-20');
        $resolved = $range->resolve();

        $this->assertEquals('2024-01-15 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-03-20 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_from_date_open_end(): void
    {
        $range = DateRangeValue::from('2024-06-01');
        $resolved = $range->resolve();

        $this->assertEquals('2024-06-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertNull($resolved->end);
    }

    public function test_until_date_open_start(): void
    {
        $range = DateRangeValue::until('2024-06-30');
        $resolved = $range->resolve();

        $this->assertNull($resolved->start);
        $this->assertEquals('2024-06-30 23:59:59', $resolved->end->toDateTimeString());
    }

    // ========================================
    // Expression Tests
    // ========================================

    public function test_expression_natural_language(): void
    {
        $range = DateRangeValue::expression('first day of last month');
        $resolved = $range->resolve();

        $this->assertEquals('2024-10-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-10-01 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_range_expression(): void
    {
        $range = DateRangeValue::rangeExpression('first day of last month', 'last day of last month');
        $resolved = $range->resolve();

        $this->assertEquals('2024-10-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-10-31 23:59:59', $resolved->end->toDateTimeString());
    }

    // ========================================
    // Serialization Tests
    // ========================================

    public function test_to_array_serialization(): void
    {
        $range = DateRangeValue::quick(QuickDateRange::THIS_MONTH);
        $array = $range->toArray();

        $this->assertEquals('quick', $array['type']);
        $this->assertEquals('this_month', $array['quick']);
    }

    public function test_from_array_deserialization(): void
    {
        $array = [
            'type' => 'quick',
            'quick' => 'this_month',
        ];

        $range = DateRangeValue::fromArray($array);
        $resolved = $range->resolve();

        $this->assertEquals('2024-11-01 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-30 23:59:59', $resolved->end->toDateTimeString());
    }

    public function test_json_round_trip(): void
    {
        $original = DateRangeValue::lastDays(30);
        $json = json_encode($original->toArray());
        $restored = DateRangeValue::fromArray(json_decode($json, true));

        $originalResolved = $original->resolve();
        $restoredResolved = $restored->resolve();

        $this->assertEquals(
            $originalResolved->start->toDateTimeString(),
            $restoredResolved->start->toDateTimeString()
        );
        $this->assertEquals(
            $originalResolved->end->toDateTimeString(),
            $restoredResolved->end->toDateTimeString()
        );
    }

    // ========================================
    // Validation Tests
    // ========================================

    public function test_invalid_month_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Month must be between 1 and 12');

        DateRangeValue::month(13);
    }

    public function test_invalid_quarter_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quarter must be between 1 and 4');

        DateRangeValue::quarter(5);
    }

    public function test_invalid_half_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Half must be 1 (H1) or 2 (H2)');

        DateRangeValue::halfYear(3);
    }

    public function test_invalid_week_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Week number must be between 1 and 53');

        DateRangeValue::week(54);
    }

    // ========================================
    // Type and Config Tests
    // ========================================

    public function test_get_type(): void
    {
        $quickRange = DateRangeValue::today();
        $relativeRange = DateRangeValue::lastDays(30);
        $specificRange = DateRangeValue::month(3);
        $customRange = DateRangeValue::between('2024-01-01', '2024-12-31');

        $this->assertEquals(DateRangeType::QUICK, $quickRange->getType());
        $this->assertEquals(DateRangeType::RELATIVE, $relativeRange->getType());
        $this->assertEquals(DateRangeType::SPECIFIC, $specificRange->getType());
        $this->assertEquals(DateRangeType::CUSTOM, $customRange->getType());
    }

    public function test_get_config(): void
    {
        $range = DateRangeValue::lastDays(30);
        $config = $range->getConfig();

        $this->assertEquals('past', $config['direction']);
        $this->assertEquals(30, $config['amount']);
        $this->assertEquals('day', $config['unit']);
    }
}
