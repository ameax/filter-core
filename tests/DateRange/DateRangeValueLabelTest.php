<?php

namespace Ameax\FilterCore\Tests\DateRange;

use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\DateUnit;
use Ameax\FilterCore\Tests\TestCase;
use Carbon\Carbon;

class DateRangeValueLabelTest extends TestCase
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

    // =========================================================================
    // QUICK LABELS
    // =========================================================================

    public function test_quick_today_label(): void
    {
        $range = DateRangeValue::today();
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_quick_this_week_label(): void
    {
        $range = DateRangeValue::thisWeek();
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_quick_this_month_label(): void
    {
        $range = DateRangeValue::thisMonth();
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_quick_this_year_label(): void
    {
        $range = DateRangeValue::thisYear();
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // RELATIVE LABELS
    // =========================================================================

    public function test_relative_last_30_days_label(): void
    {
        $range = DateRangeValue::lastDays(30);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_relative_next_7_days_label(): void
    {
        $range = DateRangeValue::nextDays(7);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_relative_older_than_label(): void
    {
        $range = DateRangeValue::olderThan(90, DateUnit::DAY);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_relative_newer_than_label(): void
    {
        $range = DateRangeValue::newerThan(30, DateUnit::DAY);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_relative_last_2_months_label(): void
    {
        $range = DateRangeValue::lastMonths(2);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // SPECIFIC LABELS
    // =========================================================================

    public function test_specific_quarter_label(): void
    {
        $range = DateRangeValue::quarter(2, yearOffset: 0);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
        $this->assertStringContainsString('Q2', $label);
    }

    public function test_specific_quarter_last_year_label(): void
    {
        $range = DateRangeValue::quarter(1, yearOffset: -1);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
        $this->assertStringContainsString('Q1', $label);
    }

    public function test_specific_half_year_label(): void
    {
        $range = DateRangeValue::halfYear(1, yearOffset: 0);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
        $this->assertStringContainsString('H1', $label);
    }

    public function test_specific_month_label(): void
    {
        $range = DateRangeValue::month(6, yearOffset: 0);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_specific_week_label(): void
    {
        $range = DateRangeValue::week(1, yearOffset: 0);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_specific_year_label(): void
    {
        $range = DateRangeValue::year(2023);
        $label = $range->toLabel();

        $this->assertEquals('2023', $label);
    }

    public function test_unit_ago_label(): void
    {
        $range = DateRangeValue::unitAgo(2, DateUnit::MONTH);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // ANNUAL RANGE LABELS
    // =========================================================================

    public function test_fiscal_year_label(): void
    {
        $range = DateRangeValue::fiscalYear(startMonth: 7, yearOffset: 0);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_fiscal_year_last_year_label(): void
    {
        $range = DateRangeValue::fiscalYear(startMonth: 7, yearOffset: -1);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_academic_year_label(): void
    {
        $range = DateRangeValue::academicYear(startMonth: 9, yearOffset: 0);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // CUSTOM LABELS
    // =========================================================================

    public function test_custom_between_label(): void
    {
        $range = DateRangeValue::between('2024-01-15', '2024-03-20');
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
        $this->assertStringContainsString('15.01.2024', $label);
        $this->assertStringContainsString('20.03.2024', $label);
    }

    public function test_custom_from_label(): void
    {
        $range = DateRangeValue::from('2024-06-01');
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    public function test_custom_until_label(): void
    {
        $range = DateRangeValue::until('2024-12-31');
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // EXPRESSION LABELS
    // =========================================================================

    public function test_expression_single_label(): void
    {
        $range = DateRangeValue::expression('first day of last month');
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
        $this->assertStringContainsString('first day of last month', $label);
    }

    public function test_expression_range_label(): void
    {
        $range = DateRangeValue::rangeExpression('first day of last month', 'last day of last month');
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // FROM ARRAY
    // =========================================================================

    public function test_label_from_array_config(): void
    {
        $config = [
            'type' => 'relative',
            'direction' => 'past',
            'amount' => 30,
            'unit' => 'day',
            'includePartial' => true,
        ];

        $range = DateRangeValue::fromArray($config);
        $label = $range->toLabel();

        $this->assertNotEmpty($label);
    }
}
