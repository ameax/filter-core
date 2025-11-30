<?php

namespace Ameax\FilterCore\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickFilterPresetSeeder extends Seeder
{
    /**
     * Seed quick filter presets.
     *
     * Common presets are active by default, others can be enabled as needed.
     */
    public function run(): void
    {
        $presets = [
            // =============================================
            // DAY
            // =============================================
            ['quick' => 'today', 'direction' => null, 'sort' => 100, 'active' => true],
            ['quick' => 'yesterday', 'direction' => 'past', 'sort' => 110, 'active' => true],
            ['quick' => 'tomorrow', 'direction' => 'future', 'sort' => 120, 'active' => true],

            // =============================================
            // WEEK
            // =============================================
            ['quick' => 'this_week', 'direction' => null, 'sort' => 200, 'active' => true],
            ['quick' => 'last_week', 'direction' => 'past', 'sort' => 210, 'active' => true],
            ['quick' => 'next_week', 'direction' => 'future', 'sort' => 220, 'active' => true],

            // =============================================
            // MONTH
            // =============================================
            ['quick' => 'this_month', 'direction' => null, 'sort' => 300, 'active' => true],
            ['quick' => 'last_month', 'direction' => 'past', 'sort' => 310, 'active' => true],
            ['quick' => 'next_month', 'direction' => 'future', 'sort' => 320, 'active' => true],

            // =============================================
            // QUARTER
            // =============================================
            ['quick' => 'this_quarter', 'direction' => null, 'sort' => 400, 'active' => true],
            ['quick' => 'last_quarter', 'direction' => 'past', 'sort' => 410, 'active' => true],
            ['quick' => 'next_quarter', 'direction' => 'future', 'sort' => 420, 'active' => false],

            // =============================================
            // HALF YEAR
            // =============================================
            ['quick' => 'this_half_year', 'direction' => null, 'sort' => 500, 'active' => false],
            ['quick' => 'last_half_year', 'direction' => 'past', 'sort' => 510, 'active' => false],
            ['quick' => 'next_half_year', 'direction' => 'future', 'sort' => 520, 'active' => false],
            ['quick' => 'h1_this_year', 'direction' => 'past', 'sort' => 530, 'active' => false],
            ['quick' => 'h2_this_year', 'direction' => 'past', 'sort' => 540, 'active' => false],
            ['quick' => 'h1_last_year', 'direction' => 'past', 'sort' => 550, 'active' => false],
            ['quick' => 'h2_last_year', 'direction' => 'past', 'sort' => 560, 'active' => false],

            // =============================================
            // YEAR
            // =============================================
            ['quick' => 'this_year', 'direction' => null, 'sort' => 600, 'active' => true],
            ['quick' => 'last_year', 'direction' => 'past', 'sort' => 610, 'active' => true],
            ['quick' => 'next_year', 'direction' => 'future', 'sort' => 620, 'active' => false],
        ];

        // Quick presets
        foreach ($presets as $preset) {
            $this->insert([
                'type' => 'quick',
                'quick' => $preset['quick'],
            ], $preset['direction'], $preset['sort'], $preset['active']);
        }

        // =============================================
        // ROLLING PERIODS - PAST
        // =============================================
        $this->insertRelative(7, 'day', 'past', 700, true);    // Last 7 days ✓
        $this->insertRelative(14, 'day', 'past', 705, false);   // Last 14 days
        $this->insertRelative(30, 'day', 'past', 710, true);    // Last 30 days ✓
        $this->insertRelative(60, 'day', 'past', 715, false);   // Last 60 days
        $this->insertRelative(90, 'day', 'past', 720, true);    // Last 90 days ✓
        $this->insertRelative(180, 'day', 'past', 725, false);  // Last 180 days
        $this->insertRelative(365, 'day', 'past', 730, false);  // Last 365 days

        $this->insertRelative(2, 'week', 'past', 740, false);   // Last 2 weeks
        $this->insertRelative(4, 'week', 'past', 745, false);   // Last 4 weeks

        $this->insertRelative(3, 'month', 'past', 750, false);  // Last 3 months
        $this->insertRelative(6, 'month', 'past', 755, false);  // Last 6 months
        $this->insertRelative(12, 'month', 'past', 760, false); // Last 12 months

        $this->insertRelative(2, 'quarter', 'past', 770, false); // Last 2 quarters
        $this->insertRelative(4, 'quarter', 'past', 775, false); // Last 4 quarters

        $this->insertRelative(2, 'year', 'past', 780, false);   // Last 2 years
        $this->insertRelative(3, 'year', 'past', 785, false);   // Last 3 years
        $this->insertRelative(5, 'year', 'past', 790, false);   // Last 5 years

        // =============================================
        // ROLLING PERIODS - FUTURE
        // =============================================
        $this->insertRelative(7, 'day', 'future', 800, true);   // Next 7 days ✓
        $this->insertRelative(14, 'day', 'future', 805, false); // Next 14 days
        $this->insertRelative(30, 'day', 'future', 810, true);  // Next 30 days ✓
        $this->insertRelative(60, 'day', 'future', 815, false); // Next 60 days
        $this->insertRelative(90, 'day', 'future', 820, false); // Next 90 days

        $this->insertRelative(2, 'week', 'future', 830, false); // Next 2 weeks
        $this->insertRelative(4, 'week', 'future', 835, false); // Next 4 weeks

        $this->insertRelative(3, 'month', 'future', 840, false); // Next 3 months
        $this->insertRelative(6, 'month', 'future', 845, false); // Next 6 months
        $this->insertRelative(12, 'month', 'future', 850, false); // Next 12 months

        $this->insertRelative(1, 'year', 'future', 860, false); // Next 1 year
        $this->insertRelative(2, 'year', 'future', 865, false); // Next 2 years

        // =============================================
        // OLDER/NEWER THAN
        // =============================================
        $this->insertOlderThan(7, 'day', 900, false);    // Older than 7 days
        $this->insertOlderThan(30, 'day', 905, false);   // Older than 30 days
        $this->insertOlderThan(90, 'day', 910, false);   // Older than 90 days
        $this->insertOlderThan(180, 'day', 915, false);  // Older than 180 days
        $this->insertOlderThan(1, 'year', 920, false);   // Older than 1 year
        $this->insertOlderThan(2, 'year', 925, false);   // Older than 2 years

        $this->insertNewerThan(7, 'day', 930, false);    // Newer than 7 days
        $this->insertNewerThan(30, 'day', 935, false);   // Newer than 30 days
        $this->insertNewerThan(90, 'day', 940, false);   // Newer than 90 days

        // =============================================
        // SPECIFIC QUARTERS - THIS YEAR
        // =============================================
        $this->insertQuarter(1, 0, 1000, false);  // Q1 this year
        $this->insertQuarter(2, 0, 1010, false);  // Q2 this year
        $this->insertQuarter(3, 0, 1020, false);  // Q3 this year
        $this->insertQuarter(4, 0, 1030, false);  // Q4 this year

        // =============================================
        // SPECIFIC QUARTERS - LAST YEAR
        // =============================================
        $this->insertQuarter(1, -1, 1040, false); // Q1 last year
        $this->insertQuarter(2, -1, 1050, false); // Q2 last year
        $this->insertQuarter(3, -1, 1060, false); // Q3 last year
        $this->insertQuarter(4, -1, 1070, false); // Q4 last year

        // =============================================
        // SPECIFIC QUARTERS - 2 YEARS AGO
        // =============================================
        $this->insertQuarter(1, -2, 1080, false); // Q1 2 years ago
        $this->insertQuarter(2, -2, 1090, false); // Q2 2 years ago
        $this->insertQuarter(3, -2, 1100, false); // Q3 2 years ago
        $this->insertQuarter(4, -2, 1110, false); // Q4 2 years ago

        // =============================================
        // SPECIFIC HALF YEARS
        // =============================================
        $this->insertHalfYear(1, 0, 1200, false);  // H1 this year
        $this->insertHalfYear(2, 0, 1210, false);  // H2 this year
        $this->insertHalfYear(1, -1, 1220, false); // H1 last year
        $this->insertHalfYear(2, -1, 1230, false); // H2 last year
        $this->insertHalfYear(1, -2, 1240, false); // H1 2 years ago
        $this->insertHalfYear(2, -2, 1250, false); // H2 2 years ago

        // =============================================
        // SPECIFIC MONTHS (examples)
        // =============================================
        for ($month = 1; $month <= 12; $month++) {
            $this->insertMonth($month, 0, 1300 + $month, false);  // This year
            $this->insertMonth($month, -1, 1320 + $month, false); // Last year
        }

        // =============================================
        // FISCAL YEAR (July-June, common in many countries)
        // =============================================
        $this->insertFiscalYear(7, 0, 1500, false);  // Current fiscal year
        $this->insertFiscalYear(7, -1, 1510, false); // Last fiscal year
        $this->insertFiscalYear(7, -2, 1520, false); // 2 fiscal years ago

        // =============================================
        // ACADEMIC YEAR (September-August)
        // =============================================
        $this->insertFiscalYear(9, 0, 1600, false);  // Current academic year
        $this->insertFiscalYear(9, -1, 1610, false); // Last academic year
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function insert(array $config, ?string $direction, int $sortOrder, bool $isActive): void
    {
        DB::table('filter_quick_presets')->insert([
            'scope' => null,
            'date_range_config' => json_encode($config),
            'direction' => $direction,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRelative(int $amount, string $unit, string $direction, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'relative',
            'direction' => $direction,
            'amount' => $amount,
            'unit' => $unit,
            'includePartial' => true,
        ], $direction, $sortOrder, $isActive);
    }

    private function insertOlderThan(int $amount, string $unit, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'relative',
            'direction' => 'past',
            'amount' => $amount,
            'unit' => $unit,
            'openStart' => true,
        ], 'past', $sortOrder, $isActive);
    }

    private function insertNewerThan(int $amount, string $unit, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'relative',
            'direction' => 'past',
            'amount' => $amount,
            'unit' => $unit,
            'endDate' => 'now',
        ], 'past', $sortOrder, $isActive);
    }

    private function insertQuarter(int $quarter, int $yearOffset, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'specific',
            'unit' => 'quarter',
            'quarter' => $quarter,
            'yearOffset' => $yearOffset,
        ], 'past', $sortOrder, $isActive);
    }

    private function insertHalfYear(int $half, int $yearOffset, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'specific',
            'unit' => 'half_year',
            'startMonth' => $half === 1 ? 1 : 7,
            'endMonth' => $half === 1 ? 6 : 12,
            'yearOffset' => $yearOffset,
        ], 'past', $sortOrder, $isActive);
    }

    private function insertMonth(int $month, int $yearOffset, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'specific',
            'unit' => 'month',
            'month' => $month,
            'yearOffset' => $yearOffset,
        ], 'past', $sortOrder, $isActive);
    }

    private function insertFiscalYear(int $startMonth, int $yearOffset, int $sortOrder, bool $isActive): void
    {
        $this->insert([
            'type' => 'annual_range',
            'startMonth' => $startMonth,
            'yearOffset' => $yearOffset,
            'rangeType' => 'fiscal',
        ], 'past', $sortOrder, $isActive);
    }
}
