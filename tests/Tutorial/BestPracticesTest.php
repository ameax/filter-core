<?php

namespace Ameax\FilterCore\Tests\Tutorial;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiNameFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\KoiVarietyFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\TestCase;

/**
 * Best Practices - Recommended Usage Patterns.
 *
 * This test demonstrates the recommended syntax for common filtering scenarios.
 *
 * Test Data:
 * - Showa:  status=active,  count=10, variety=Gosanke
 * - Kohaku: status=active,  count=20, variety=Gosanke
 * - Sanke:  status=inactive, count=5, variety=Gosanke
 * - Asagi:  status=pending, count=15, variety=null
 * - Shusui: status=pending, count=0,  variety=null
 */
class BestPracticesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Asagi', 'status' => 'pending', 'count' => 15, 'is_active' => true, 'variety' => null]);
        Koi::create(['name' => 'Shusui', 'status' => 'pending', 'count' => 0, 'is_active' => false, 'variety' => null]);
    }

    // ========================================================================
    // SINGLE FILTER - Using FilterValue
    // ========================================================================

    /**
     * Single Filter: Use FilterValue::for() for one-off filters.
     *
     * Best for: Quick single-filter queries
     */
    public function test_single_filter_with_filter_value(): void
    {
        // Recommended: FilterValue::for() with fluent method
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        $this->assertCount(2, $result);
    }

    // ========================================================================
    // MULTIPLE FILTERS (AND) - Using FilterSelection
    // ========================================================================

    /**
     * Multiple Filters with AND: Use FilterSelection::make()->where()
     *
     * Best for: Multiple conditions that must ALL match
     */
    public function test_multiple_filters_with_and(): void
    {
        // Recommended: Chain ->where() calls
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gte(15);

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }

    // ========================================================================
    // OR LOGIC - Using orWhere()
    // ========================================================================

    /**
     * Simple OR: Use makeOr() for conditions combined with OR.
     *
     * SQL: WHERE status = 'active' OR status = 'pending'
     */
    public function test_simple_or_with_make_or(): void
    {
        // Use makeOr() to create an OR-based selection
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(4, $result);
    }

    /**
     * Alternative: Use any() for multiple values on same filter.
     *
     * SQL: WHERE status IN ('active', 'pending')
     */
    public function test_any_instead_of_multiple_or(): void
    {
        // Better: Use any() instead of multiple orWhere()
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->any(['active', 'pending']);

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(4, $result);
    }

    // ========================================================================
    // COMPLEX OR - Grouped Conditions
    // ========================================================================

    /**
     * Grouped OR: Use makeOr() + andWhere() for (A) OR (B AND C) pattern.
     *
     * SQL: WHERE status = 'active' OR (status = 'pending' AND count >= 10)
     */
    public function test_grouped_or_conditions(): void
    {
        // Use makeOr() for OR-based root, then andWhere() for grouped conditions
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->andWhere(function ($group) {
                $group->where(KoiStatusFilter::class)->is('pending');
                $group->where(KoiCountFilter::class)->gte(10);
            });

        $result = Koi::query()->applySelection($selection)->get();

        // active: Showa, Kohaku
        // pending AND count >= 10: Asagi
        $this->assertCount(3, $result);
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    // ========================================================================
    // RANGE FILTERS
    // ========================================================================

    /**
     * Range: Use between() for inclusive ranges.
     */
    public function test_range_with_between(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiCountFilter::class)->between(5, 15);

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(3, $result); // 5, 10, 15
    }

    /**
     * Open-ended Range: Combine gt/lt for exclusive bounds.
     */
    public function test_open_ended_range(): void
    {
        // count > 5 AND count < 20
        $selection = FilterSelection::make()
            ->where(KoiCountFilter::class)->gt(5)
            ->where(KoiCountFilter::class)->lt(20);

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(2, $result); // 10, 15
    }

    // ========================================================================
    // TEXT SEARCH
    // ========================================================================

    /**
     * Text Search: Use contains() for substring matching.
     */
    public function test_text_search_contains(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiNameFilter::class)->contains('a');

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(4, $result); // Showa, Kohaku, Sanke, Asagi
    }

    /**
     * Prefix Search: Use startsWith() for autocomplete-style matching.
     */
    public function test_text_search_starts_with(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiNameFilter::class)->startsWith('Sh');

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(2, $result); // Showa, Shusui
    }

    // ========================================================================
    // NULL HANDLING
    // ========================================================================

    /**
     * Find Empty: Use empty() for NULL or empty string values.
     */
    public function test_find_empty_values(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiVarietyFilter::class)->empty();

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(2, $result); // Asagi, Shusui
    }

    /**
     * Find Non-Empty: Use notEmpty() for records with values.
     */
    public function test_find_non_empty_values(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiVarietyFilter::class)->notEmpty();

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(3, $result); // Showa, Kohaku, Sanke
    }

    // ========================================================================
    // EXCLUSION FILTERS
    // ========================================================================

    /**
     * Exclude Values: Use isNot() for single exclusion.
     */
    public function test_exclude_single_value(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->isNot('inactive');

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(4, $result);
    }

    /**
     * Exclude Multiple: Use none() to exclude multiple values.
     */
    public function test_exclude_multiple_values(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->none(['inactive', 'pending']);

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(2, $result); // only active
    }

    // ========================================================================
    // REUSABLE SELECTIONS
    // ========================================================================

    /**
     * Named Selection: Give selections a name for later reference.
     */
    public function test_named_selection(): void
    {
        $activeHighCount = FilterSelection::make('Active with High Count')
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gte(15);

        $result = Koi::query()->applySelection($activeHighCount)->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Active with High Count', $activeHighCount->getName());
    }

    // ========================================================================
    // JSON SERIALIZATION
    // ========================================================================

    /**
     * Save & Load: Serialize selections for persistence.
     */
    public function test_json_serialization(): void
    {
        // Create selection
        $selection = FilterSelection::make('My Filters')
            ->where(KoiStatusFilter::class)->any(['active', 'pending'])
            ->where(KoiCountFilter::class)->gte(10);

        // Serialize to JSON
        $json = $selection->toJson();

        // Later: Load from JSON
        $loaded = FilterSelection::fromJson($json);

        // Apply loaded selection
        $result = Koi::query()->applySelection($loaded)->get();

        $this->assertCount(3, $result);
        $this->assertEquals('My Filters', $loaded->getName());
    }

    // ========================================================================
    // COMBINING EVERYTHING
    // ========================================================================

    /**
     * Complex Query: Real-world example with (A AND B) OR (C AND D) pattern.
     *
     * Find koi that are either:
     * - active with count >= 10
     * - OR pending with variety set
     *
     * Use makeOr() for OR-based root, then andWhere() for each AND group.
     */
    public function test_complex_real_world_query(): void
    {
        // (status = 'active' AND count >= 10) OR (status = 'pending' AND variety IS NOT NULL)
        $selection = FilterSelection::makeOr('Premium Koi Search')
            ->andWhere(function ($group) {
                $group->where(KoiStatusFilter::class)->is('active');
                $group->where(KoiCountFilter::class)->gte(10);
            })
            ->andWhere(function ($group) {
                $group->where(KoiStatusFilter::class)->is('pending');
                $group->where(KoiVarietyFilter::class)->notEmpty();
            });

        $result = Koi::query()->applySelection($selection)->get();

        // active AND count >= 10: Showa (10), Kohaku (20)
        // pending AND variety not empty: none (Asagi, Shusui have null variety)
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }
}
