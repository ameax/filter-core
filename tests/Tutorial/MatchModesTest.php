<?php

namespace Ameax\FilterCore\Tests\Tutorial;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiNameFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\KoiVarietyFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\TestCase;

/**
 * MatchModes Tutorial - All 17 MatchModes Explained.
 *
 * This test demonstrates each MatchMode with real database queries.
 *
 * Test Data:
 * - Showa:  status=active,  count=10, name starts with "Sh", variety=Gosanke
 * - Kohaku: status=active,  count=20, name ends with "ku", variety=Gosanke
 * - Sanke:  status=inactive, count=5,  variety=Gosanke
 * - Asagi:  status=pending, count=15, name starts with "A", variety=null
 * - Shusui: status=pending, count=0,  name starts with "Sh", variety=null
 */
class MatchModesTest extends TestCase
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
    // EQUALITY MODES
    // ========================================================================

    /**
     * IS Mode: Exact match (single value or array).
     *
     * SQL: WHERE column = 'value'
     * SQL: WHERE column IN ('a', 'b') -- for arrays
     */
    public function test_is_mode(): void
    {
        // Single value
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * IS_NOT Mode: Not equal.
     *
     * SQL: WHERE column != 'value'
     * SQL: WHERE column NOT IN ('a', 'b') -- for arrays
     */
    public function test_is_not_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->isNot('active'))
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status !== 'active'));
    }

    // ========================================================================
    // MULTI-VALUE MODES
    // ========================================================================

    /**
     * ANY Mode: At least one value matches (OR logic).
     *
     * SQL: WHERE column IN ('a', 'b', 'c')
     */
    public function test_any_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->any(['active', 'pending']))
            ->get();

        $this->assertCount(4, $result);
        $this->assertTrue($result->every(fn ($koi) => in_array($koi->status, ['active', 'pending'])));
    }

    /**
     * ALL Mode: All values must match.
     *
     * For regular columns: Only possible with single value (behaves like IS).
     * For JSON/relations: All values must be present.
     */
    public function test_all_mode(): void
    {
        // With single value: behaves like IS
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->all(['active']))
            ->get();

        $this->assertCount(2, $result);

        // With multiple values on regular column: impossible, returns empty
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->all(['active', 'pending']))
            ->get();

        $this->assertCount(0, $result);
    }

    /**
     * NONE Mode: No value may match.
     *
     * SQL: WHERE column NOT IN ('a', 'b', 'c')
     */
    public function test_none_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->none(['active', 'inactive']))
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'pending'));
    }

    // ========================================================================
    // COMPARISON MODES
    // ========================================================================

    /**
     * GT Mode: Greater than.
     *
     * SQL: WHERE column > value
     */
    public function test_gt_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->gt(10))
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count > 10));
        $this->assertEquals(['Asagi', 'Kohaku'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * GTE Mode: Greater than or equal.
     *
     * SQL: WHERE column >= value
     */
    public function test_gte_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->gte(10))
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count >= 10));
    }

    /**
     * LT Mode: Less than.
     *
     * SQL: WHERE column < value
     */
    public function test_lt_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->lt(10))
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count < 10));
        $this->assertEquals(['Sanke', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * LTE Mode: Less than or equal.
     *
     * SQL: WHERE column <= value
     */
    public function test_lte_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->lte(10))
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count <= 10));
    }

    /**
     * BETWEEN Mode: Value in range (inclusive).
     *
     * SQL: WHERE column BETWEEN min AND max
     */
    public function test_between_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->between(5, 15))
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count >= 5 && $koi->count <= 15));
    }

    // ========================================================================
    // TEXT SEARCH MODES
    // ========================================================================

    /**
     * CONTAINS Mode: Text contains substring.
     *
     * SQL: WHERE column LIKE '%value%'
     */
    public function test_contains_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->contains('a'))
            ->get();

        $this->assertCount(4, $result);
        $this->assertEquals(['Asagi', 'Kohaku', 'Sanke', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * CONTAINS_ALL Mode: All whitespace-separated tokens must be contained.
     *
     * SQL: WHERE (column LIKE '%token1%' AND column LIKE '%token2%' ...)
     */
    public function test_contains_all_mode(): void
    {
        // Two tokens, both present in the same name
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->containsAll('Sh wa'))
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Showa', $result->first()->name);

        // Tokens not all present → no match
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->containsAll('Sh xx'))
            ->get();

        $this->assertCount(0, $result);

        // Single token behaves like contains
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->containsAll('Sh'))
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Showa', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * TextFilter also permits EMPTY / NOT_EMPTY modes without tripping the
     * required-value validation. Koi.name is non-nullable so empty() returns
     * nothing and notEmpty() returns every row.
     */
    public function test_text_filter_allows_empty_and_not_empty_modes(): void
    {
        $empty = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->empty())
            ->get();

        $this->assertCount(0, $empty);

        $notEmpty = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->notEmpty())
            ->get();

        $this->assertCount(5, $notEmpty);
    }

    /**
     * STARTS_WITH Mode: Text starts with prefix.
     *
     * SQL: WHERE column LIKE 'value%'
     */
    public function test_starts_with_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->startsWith('Sh'))
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Showa', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * ENDS_WITH Mode: Text ends with suffix.
     *
     * SQL: WHERE column LIKE '%value'
     */
    public function test_ends_with_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->endsWith('ke'))
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Sanke', $result->first()->name);
    }

    /**
     * REGEX Mode: Regular expression match (MySQL).
     *
     * SQL: WHERE column REGEXP 'pattern'
     */
    public function test_regex_mode(): void
    {
        // Match names starting with S
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->regex('^S'))
            ->get();

        $this->assertCount(3, $result);
        $this->assertEquals(['Sanke', 'Showa', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    // ========================================================================
    // NULL HANDLING MODES
    // ========================================================================

    /**
     * EMPTY Mode: Column is NULL or empty string.
     *
     * SQL: WHERE column IS NULL OR column = ''
     */
    public function test_empty_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiVarietyFilter::class)->empty())
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Asagi', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * NOT_EMPTY Mode: Column is not NULL and not empty.
     *
     * SQL: WHERE column IS NOT NULL AND column != ''
     */
    public function test_not_empty_mode(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiVarietyFilter::class)->notEmpty())
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->variety !== null));
    }

    // ========================================================================
    // COMBINED USAGE
    // ========================================================================

    /**
     * Multiple modes can be combined in a FilterSelection.
     */
    public function test_combined_modes(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->any(['active', 'pending'])
            ->where(KoiCountFilter::class)->gte(10)
            ->where(KoiNameFilter::class)->startsWith('S');

        $result = Koi::query()->applySelection($selection)->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Showa', $result->first()->name);
    }
}
