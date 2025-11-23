<?php

namespace Ameax\FilterCore\Tests\Concerns;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondCapacityFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

class FilterableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear filter cache before each test
        Koi::clearFilterCache();
        Pond::clearFilterCache();

        // Create ponds
        $freshPond = Pond::create(['name' => 'Fresh Pond', 'water_type' => 'fresh', 'capacity' => 5000, 'is_heated' => true]);
        $saltPond = Pond::create(['name' => 'Salt Pond', 'water_type' => 'salt', 'capacity' => 3000, 'is_heated' => false]);
        $brackishPond = Pond::create(['name' => 'Brackish Pond', 'water_type' => 'brackish', 'capacity' => 2000, 'is_heated' => true]);

        // Create kois in ponds
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'pond_id' => $saltPond->id]);
        Koi::create(['name' => 'Asagi', 'status' => 'pending', 'count' => 15, 'is_active' => true, 'pond_id' => $brackishPond->id]);
        Koi::create(['name' => 'Shusui', 'status' => 'pending', 'count' => 0, 'is_active' => false, 'pond_id' => null]);
    }

    // ========================================
    // Filterable Trait - Basic Tests
    // ========================================

    public function test_model_returns_filters_via_get_filters(): void
    {
        $filters = Koi::getFilters();

        $this->assertCount(6, $filters);
    }

    public function test_filters_are_cached(): void
    {
        $filters1 = Koi::getFilters();
        $filters2 = Koi::getFilters();

        // Should be exact same array instance (cached)
        $this->assertSame($filters1, $filters2);
    }

    public function test_clear_filter_cache_clears_cache(): void
    {
        $filters1 = Koi::getFilters();
        Koi::clearFilterCache();
        $filters2 = Koi::getFilters();

        // Should be different array instances after clearing
        $this->assertNotSame($filters1, $filters2);
        // But same content
        $this->assertCount(count($filters1), $filters2);
    }

    // ========================================
    // Filterable Trait - Query Scope Tests
    // ========================================

    public function test_scope_apply_filter_with_single_filter(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'active'));
    }

    public function test_scope_apply_filters_with_multiple_filters(): void
    {
        $result = Koi::query()
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->is('active'),
                FilterValue::for(KoiCountFilter::class)->greaterThan(5),
            ])
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'active' && $koi->count > 5));
    }

    public function test_scope_apply_filters_with_empty_array(): void
    {
        $result = Koi::query()
            ->applyFilters([])
            ->get();

        // Should return all kois
        $this->assertCount(5, $result);
    }

    public function test_scope_apply_filter_with_relation_filter(): void
    {
        $result = Koi::query()
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->is('fresh'))
            ->get();

        // Showa and Kohaku are in the fresh pond
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_scope_apply_filters_with_combined_filters(): void
    {
        $result = Koi::query()
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->is('active'),
                FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),
            ])
            ->get();

        // Active kois in fresh pond: Showa, Kohaku
        $this->assertCount(2, $result);
    }

    public function test_scope_can_be_chained_with_other_queries(): void
    {
        $result = Koi::query()
            ->where('count', '>', 0)
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }

    // ========================================
    // Filterable Trait - Pond Model Tests
    // ========================================

    public function test_pond_model_has_filters(): void
    {
        $filters = Pond::getFilters();

        $this->assertCount(3, $filters);
    }

    public function test_pond_scope_apply_filter(): void
    {
        $result = Pond::query()
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->is('fresh'))
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Fresh Pond', $result->first()->name);
    }

    public function test_pond_scope_apply_filters_with_integer_comparison(): void
    {
        $result = Pond::query()
            ->applyFilters([
                FilterValue::for(PondCapacityFilter::class)->greaterThan(2500),
            ])
            ->get();

        // Fresh Pond (5000) and Salt Pond (3000)
        $this->assertCount(2, $result);
    }

    // ========================================
    // Simplified Syntax Examples
    // ========================================

    public function test_simple_filter_syntax(): void
    {
        // This is the final simplified syntax!
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        $this->assertCount(2, $result);
    }

    public function test_multiple_filters_syntax(): void
    {
        // Multiple filters
        $result = Koi::query()
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->any(['active', 'pending']),
                FilterValue::for(KoiCountFilter::class)->between(5, 15),
            ])
            ->get();

        // Active or pending with count 5-15: Showa (active, 10), Asagi (pending, 15)
        // Sanke is inactive (not matched), Kohaku has count=20 (not matched), Shusui has count=0 (not matched)
        $this->assertCount(2, $result);
        $this->assertEquals(['Asagi', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_relation_filter_syntax(): void
    {
        // Relation filters are automatically applied via whereHas
        $result = Koi::query()
            ->applyFilter(FilterValue::for(PondCapacityFilter::class)->greaterThan(4000))
            ->get();

        // Only fresh pond has capacity > 4000
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }
}
