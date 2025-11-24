<?php

namespace Ameax\FilterCore\Tests\Collection;

use Ameax\FilterCore\Collection\CollectionApplicator;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiNameFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\KoiVarietyFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\TestCase;
use Illuminate\Support\Collection;

/**
 * Tests for CollectionApplicator.
 *
 * These tests verify that collection filtering produces the same results
 * as query filtering for in-memory collections.
 */
class CollectionApplicatorTest extends TestCase
{
    protected Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data in database
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Asagi', 'status' => 'pending', 'count' => 15, 'is_active' => true, 'variety' => null]);
        Koi::create(['name' => 'Shusui', 'status' => 'pending', 'count' => 0, 'is_active' => false, 'variety' => null]);

        // Load collection for filtering
        $this->collection = Koi::all();
    }

    // ========================================================================
    // Basic Filtering Tests
    // ========================================================================

    public function test_filter_collection_with_is_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->getCollection();

        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_collection_with_is_not_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->isNot('active'))
            ->getCollection();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status !== 'active'));
    }

    public function test_filter_collection_with_any_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->any(['active', 'pending']))
            ->getCollection();

        $this->assertCount(4, $result);
    }

    public function test_filter_collection_with_none_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->none(['active', 'inactive']))
            ->getCollection();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'pending'));
    }

    // ========================================================================
    // Comparison Mode Tests
    // ========================================================================

    public function test_filter_collection_with_gt_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiCountFilter::class])
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->gt(10))
            ->getCollection();

        $this->assertCount(2, $result);
        $this->assertEquals(['Asagi', 'Kohaku'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_collection_with_gte_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiCountFilter::class])
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->gte(10))
            ->getCollection();

        $this->assertCount(3, $result);
    }

    public function test_filter_collection_with_lt_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiCountFilter::class])
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->lt(10))
            ->getCollection();

        $this->assertCount(2, $result);
        $this->assertEquals(['Sanke', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_collection_with_lte_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiCountFilter::class])
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->lte(10))
            ->getCollection();

        $this->assertCount(3, $result);
    }

    public function test_filter_collection_with_between_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiCountFilter::class])
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->between(5, 15))
            ->getCollection();

        $this->assertCount(3, $result);
    }

    // ========================================================================
    // Text Search Mode Tests
    // ========================================================================

    public function test_filter_collection_with_contains_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiNameFilter::class])
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->contains('a'))
            ->getCollection();

        $this->assertCount(4, $result);
        $this->assertEquals(['Asagi', 'Kohaku', 'Sanke', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_collection_with_starts_with_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiNameFilter::class])
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->startsWith('Sh'))
            ->getCollection();

        $this->assertCount(2, $result);
        $this->assertEquals(['Showa', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_collection_with_ends_with_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiNameFilter::class])
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->endsWith('ke'))
            ->getCollection();

        $this->assertCount(1, $result);
        $this->assertEquals('Sanke', $result->first()->name);
    }

    public function test_filter_collection_with_regex_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiNameFilter::class])
            ->applyFilter(FilterValue::for(KoiNameFilter::class)->regex('^S'))
            ->getCollection();

        $this->assertCount(3, $result);
        $this->assertEquals(['Sanke', 'Showa', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    // ========================================================================
    // Null Handling Mode Tests
    // ========================================================================

    public function test_filter_collection_with_empty_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiVarietyFilter::class])
            ->applyFilter(FilterValue::for(KoiVarietyFilter::class)->empty())
            ->getCollection();

        $this->assertCount(2, $result);
        $this->assertEquals(['Asagi', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_collection_with_not_empty_mode(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiVarietyFilter::class])
            ->applyFilter(FilterValue::for(KoiVarietyFilter::class)->notEmpty())
            ->getCollection();

        $this->assertCount(3, $result);
    }

    // ========================================================================
    // Multiple Filters Tests
    // ========================================================================

    public function test_filter_collection_with_multiple_filters(): void
    {
        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class, KoiCountFilter::class])
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->is('active'),
                FilterValue::for(KoiCountFilter::class)->gte(15),
            ])
            ->getCollection();

        $this->assertCount(1, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }

    // ========================================================================
    // FilterSelection Tests
    // ========================================================================

    public function test_filter_collection_with_selection(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gte(15);

        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class, KoiCountFilter::class])
            ->applySelection($selection)
            ->getCollection();

        $this->assertCount(1, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }

    public function test_filter_collection_with_or_selection(): void
    {
        // status = 'active' OR status = 'pending'
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class])
            ->applySelection($selection)
            ->getCollection();

        $this->assertCount(4, $result);
    }

    public function test_filter_collection_with_complex_or_selection(): void
    {
        // (status = 'active' AND count >= 15) OR (status = 'pending')
        $selection = FilterSelection::makeOr()
            ->andWhere(function ($g) {
                $g->where(KoiStatusFilter::class)->is('active');
                $g->where(KoiCountFilter::class)->gte(15);
            })
            ->andWhere(function ($g) {
                $g->where(KoiStatusFilter::class)->is('pending');
            });

        $result = CollectionApplicator::for($this->collection)
            ->withFilters([KoiStatusFilter::class, KoiCountFilter::class])
            ->applySelection($selection)
            ->getCollection();

        // active AND count >= 15: Kohaku
        // pending: Asagi, Shusui
        $this->assertCount(3, $result);
        $this->assertEquals(['Asagi', 'Kohaku', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    // ========================================================================
    // Model Integration Tests
    // ========================================================================

    public function test_model_filter_collection_method(): void
    {
        $result = Koi::filterCollection($this->collection, [
            FilterValue::for(KoiStatusFilter::class)->is('active'),
        ]);

        $this->assertCount(2, $result);
    }

    public function test_model_filter_collection_with_selection(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->any(['active', 'pending'])
            ->where(KoiCountFilter::class)->gte(10);

        $result = Koi::filterCollectionWithSelection($this->collection, $selection);

        $this->assertCount(3, $result);
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    // ========================================================================
    // Query vs Collection Comparison Tests
    // ========================================================================

    public function test_collection_filtering_matches_query_filtering(): void
    {
        $filters = [
            FilterValue::for(KoiStatusFilter::class)->any(['active', 'pending']),
            FilterValue::for(KoiCountFilter::class)->gte(10),
        ];

        // Query filtering
        $queryResult = Koi::query()
            ->applyFilters($filters)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        // Collection filtering
        $collectionResult = Koi::filterCollection($this->collection, $filters)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertEquals($queryResult, $collectionResult);
    }

    public function test_collection_or_filtering_matches_query_filtering(): void
    {
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        // Query filtering
        $queryResult = Koi::query()
            ->applySelection($selection)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        // Collection filtering
        $collectionResult = Koi::filterCollectionWithSelection($this->collection, $selection)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertEquals($queryResult, $collectionResult);
    }
}
