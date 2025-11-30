<?php

namespace Ameax\FilterCore\Tests\DateRange;

use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\DateRange\QuickDateRange;
use Ameax\FilterCore\Tests\TestCase;
use Carbon\Carbon;

class QuickDateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 11, 15, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ========================================
    // Direction Tests
    // ========================================

    public function test_past_ranges_have_past_direction(): void
    {
        $this->assertEquals(DateDirection::PAST, QuickDateRange::TODAY->direction());
        $this->assertEquals(DateDirection::PAST, QuickDateRange::YESTERDAY->direction());
        $this->assertEquals(DateDirection::PAST, QuickDateRange::THIS_WEEK->direction());
        $this->assertEquals(DateDirection::PAST, QuickDateRange::LAST_WEEK->direction());
        $this->assertEquals(DateDirection::PAST, QuickDateRange::THIS_MONTH->direction());
        $this->assertEquals(DateDirection::PAST, QuickDateRange::LAST_MONTH->direction());
        $this->assertEquals(DateDirection::PAST, QuickDateRange::LAST_30_DAYS->direction());
    }

    public function test_future_ranges_have_future_direction(): void
    {
        $this->assertEquals(DateDirection::FUTURE, QuickDateRange::TOMORROW->direction());
        $this->assertEquals(DateDirection::FUTURE, QuickDateRange::NEXT_WEEK->direction());
        $this->assertEquals(DateDirection::FUTURE, QuickDateRange::NEXT_MONTH->direction());
        $this->assertEquals(DateDirection::FUTURE, QuickDateRange::NEXT_QUARTER->direction());
        $this->assertEquals(DateDirection::FUTURE, QuickDateRange::NEXT_YEAR->direction());
        $this->assertEquals(DateDirection::FUTURE, QuickDateRange::NEXT_7_DAYS->direction());
    }

    // ========================================
    // Resolution Tests
    // ========================================

    public function test_this_half_year_h2(): void
    {
        // November is in H2
        $resolved = QuickDateRange::THIS_HALF_YEAR->resolve();

        $this->assertEquals('2024-07-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2024-12-31 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_this_half_year_h1(): void
    {
        // Set to March (H1)
        Carbon::setTestNow(Carbon::create(2024, 3, 15, 12, 0, 0));

        $resolved = QuickDateRange::THIS_HALF_YEAR->resolve();

        $this->assertEquals('2024-01-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_last_half_year(): void
    {
        // November is in H2, so last half year is H1
        $resolved = QuickDateRange::LAST_HALF_YEAR->resolve();

        $this->assertEquals('2024-01-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_h1_this_year(): void
    {
        $resolved = QuickDateRange::H1_THIS_YEAR->resolve();

        $this->assertEquals('2024-01-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_h2_this_year(): void
    {
        $resolved = QuickDateRange::H2_THIS_YEAR->resolve();

        $this->assertEquals('2024-07-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2024-12-31 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_h1_last_year(): void
    {
        $resolved = QuickDateRange::H1_LAST_YEAR->resolve();

        $this->assertEquals('2023-01-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2023-06-30 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_h2_last_year(): void
    {
        $resolved = QuickDateRange::H2_LAST_YEAR->resolve();

        $this->assertEquals('2023-07-01 00:00:00', $resolved['start']->toDateTimeString());
        $this->assertEquals('2023-12-31 23:59:59', $resolved['end']->toDateTimeString());
    }

    public function test_q1_through_q4_this_year(): void
    {
        $q1 = QuickDateRange::Q1_THIS_YEAR->resolve();
        $this->assertEquals('2024-01-01 00:00:00', $q1['start']->toDateTimeString());
        $this->assertEquals('2024-03-31 23:59:59', $q1['end']->toDateTimeString());

        $q2 = QuickDateRange::Q2_THIS_YEAR->resolve();
        $this->assertEquals('2024-04-01 00:00:00', $q2['start']->toDateTimeString());
        $this->assertEquals('2024-06-30 23:59:59', $q2['end']->toDateTimeString());

        $q3 = QuickDateRange::Q3_THIS_YEAR->resolve();
        $this->assertEquals('2024-07-01 00:00:00', $q3['start']->toDateTimeString());
        $this->assertEquals('2024-09-30 23:59:59', $q3['end']->toDateTimeString());

        $q4 = QuickDateRange::Q4_THIS_YEAR->resolve();
        $this->assertEquals('2024-10-01 00:00:00', $q4['start']->toDateTimeString());
        $this->assertEquals('2024-12-31 23:59:59', $q4['end']->toDateTimeString());
    }

    // ========================================
    // Grouping Tests
    // ========================================

    public function test_grouped_returns_all_categories(): void
    {
        $groups = QuickDateRange::grouped();

        $this->assertArrayHasKey('day', $groups);
        $this->assertArrayHasKey('week', $groups);
        $this->assertArrayHasKey('month', $groups);
        $this->assertArrayHasKey('quarter', $groups);
        $this->assertArrayHasKey('half_year', $groups);
        $this->assertArrayHasKey('year', $groups);
        $this->assertArrayHasKey('rolling', $groups);
    }

    public function test_half_year_group_contains_all_half_year_options(): void
    {
        $groups = QuickDateRange::grouped();

        $this->assertContains(QuickDateRange::THIS_HALF_YEAR, $groups['half_year']);
        $this->assertContains(QuickDateRange::LAST_HALF_YEAR, $groups['half_year']);
        $this->assertContains(QuickDateRange::NEXT_HALF_YEAR, $groups['half_year']);
        $this->assertContains(QuickDateRange::H1_THIS_YEAR, $groups['half_year']);
        $this->assertContains(QuickDateRange::H2_THIS_YEAR, $groups['half_year']);
        $this->assertContains(QuickDateRange::H1_LAST_YEAR, $groups['half_year']);
        $this->assertContains(QuickDateRange::H2_LAST_YEAR, $groups['half_year']);
    }

    public function test_for_direction_filters_by_direction(): void
    {
        $pastRanges = QuickDateRange::forDirection(DateDirection::PAST);
        $futureRanges = QuickDateRange::forDirection(DateDirection::FUTURE);

        // All past ranges should have PAST direction
        foreach ($pastRanges as $range) {
            $this->assertEquals(DateDirection::PAST, $range->direction());
        }

        // All future ranges should have FUTURE direction
        foreach ($futureRanges as $range) {
            $this->assertEquals(DateDirection::FUTURE, $range->direction());
        }

        // Should have more past than future options
        $this->assertGreaterThan(count($futureRanges), count($pastRanges));
    }

    // ========================================
    // Label Tests
    // ========================================

    public function test_labels_return_strings(): void
    {
        foreach (QuickDateRange::cases() as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }
}
