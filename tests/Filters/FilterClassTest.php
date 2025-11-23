<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

class FilterClassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    // Filter Class Tests
    // ========================================

    public function test_filter_class_returns_correct_type(): void
    {
        $filter = KoiStatusFilter::make();

        $this->assertEquals(FilterTypeEnum::SELECT, $filter->type());
        $this->assertEquals('status', $filter->column());
        $this->assertEquals('Koi Status', $filter->label());
    }

    public function test_filter_class_returns_options(): void
    {
        $filter = KoiStatusFilter::make();

        $this->assertEquals([
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
        ], $filter->options());
    }

    public function test_filter_class_returns_allowed_modes(): void
    {
        $filter = KoiStatusFilter::make();

        $this->assertEquals([
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
            MatchModeEnum::ANY,
            MatchModeEnum::NONE,
        ], $filter->allowedModes());
    }

    public function test_filter_class_key_is_class_basename(): void
    {
        $this->assertEquals('KoiStatusFilter', KoiStatusFilter::key());
        $this->assertEquals('KoiCountFilter', KoiCountFilter::key());
    }

    public function test_filter_converts_to_definition(): void
    {
        $filter = KoiStatusFilter::make();
        $definition = $filter->toDefinition();

        $this->assertEquals('KoiStatusFilter', $definition->getKey());
        $this->assertEquals(FilterTypeEnum::SELECT, $definition->getType());
        $this->assertEquals('status', $definition->getColumn());
        $this->assertEquals('Koi Status', $definition->getLabel());
    }

    // ========================================
    // Fluent FilterValue Syntax Tests
    // ========================================

    public function test_filter_value_fluent_with_default_mode(): void
    {
        // SelectFilter default mode is IS
        $filterValue = FilterValue::for(KoiStatusFilter::class)->value('active');

        $this->assertEquals('KoiStatusFilter', $filterValue->getFilterKey());
        $this->assertEquals(MatchModeEnum::IS, $filterValue->getMatchMode());
        $this->assertEquals('active', $filterValue->getValue());
    }

    public function test_filter_value_fluent_with_explicit_mode(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)
            ->mode(MatchModeEnum::ANY)
            ->value(['active', 'pending']);

        $this->assertEquals(MatchModeEnum::ANY, $filterValue->getMatchMode());
        $this->assertEquals(['active', 'pending'], $filterValue->getValue());
    }

    public function test_filter_value_fluent_is_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)->is('active');

        $this->assertEquals(MatchModeEnum::IS, $filterValue->getMatchMode());
        $this->assertEquals('active', $filterValue->getValue());
    }

    public function test_filter_value_fluent_is_not_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)->isNot('inactive');

        $this->assertEquals(MatchModeEnum::IS_NOT, $filterValue->getMatchMode());
        $this->assertEquals('inactive', $filterValue->getValue());
    }

    public function test_filter_value_fluent_any_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)->any(['active', 'pending']);

        $this->assertEquals(MatchModeEnum::ANY, $filterValue->getMatchMode());
        $this->assertEquals(['active', 'pending'], $filterValue->getValue());
    }

    public function test_filter_value_fluent_none_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)->none(['inactive']);

        $this->assertEquals(MatchModeEnum::NONE, $filterValue->getMatchMode());
        $this->assertEquals(['inactive'], $filterValue->getValue());
    }

    public function test_filter_value_fluent_greater_than_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiCountFilter::class)->greaterThan(10);

        $this->assertEquals(MatchModeEnum::GREATER_THAN, $filterValue->getMatchMode());
        $this->assertEquals(10, $filterValue->getValue());
    }

    public function test_filter_value_fluent_less_than_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiCountFilter::class)->lessThan(10);

        $this->assertEquals(MatchModeEnum::LESS_THAN, $filterValue->getMatchMode());
        $this->assertEquals(10, $filterValue->getValue());
    }

    public function test_filter_value_fluent_between_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiCountFilter::class)->between(5, 15);

        $this->assertEquals(MatchModeEnum::BETWEEN, $filterValue->getMatchMode());
        $this->assertEquals(['min' => 5, 'max' => 15], $filterValue->getValue());
    }

    public function test_filter_value_fluent_contains_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiNameFilter::class)->contains('Sh');

        $this->assertEquals(MatchModeEnum::CONTAINS, $filterValue->getMatchMode());
        $this->assertEquals('Sh', $filterValue->getValue());
    }

    // ========================================
    // QueryApplicator with Filter Classes
    // ========================================

    public function test_query_applicator_with_filter_classes(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
    }

    public function test_query_applicator_with_filter_instances(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiStatusFilter::make()])
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
    }

    public function test_query_applicator_with_multiple_filters(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                KoiStatusFilter::class,
                KoiCountFilter::class,
            ])
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->is('active'),
                FilterValue::for(KoiCountFilter::class)->greaterThan(5),
            ])
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'active' && $koi->count > 5));
    }

    // ========================================
    // Relation Filter Tests
    // ========================================

    public function test_filter_via_relation(): void
    {
        // PondWaterTypeFilter applies to pond.water_type via 'pond' relation
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                PondWaterTypeFilter::via('pond'),
            ])
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->is('fresh'))
            ->getQuery()
            ->get();

        // Showa and Kohaku are in the fresh pond
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_filter_via_relation_with_any(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                PondWaterTypeFilter::via('pond'),
            ])
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->any(['fresh', 'brackish']))
            ->getQuery()
            ->get();

        // Showa, Kohaku (fresh), Asagi (brackish)
        $this->assertCount(3, $result);
    }

    public function test_filter_via_relation_with_integer_comparison(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                PondCapacityFilter::via('pond'),
            ])
            ->applyFilter(FilterValue::for(PondCapacityFilter::class)->greaterThan(2500))
            ->getQuery()
            ->get();

        // Fresh pond (5000) and Salt pond (3000) - Showa, Kohaku, Sanke
        $this->assertCount(3, $result);
    }

    public function test_combined_direct_and_relation_filters(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                KoiStatusFilter::class,
                PondWaterTypeFilter::via('pond'),
            ])
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->is('active'),
                FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),
            ])
            ->getQuery()
            ->get();

        // Active kois in fresh pond: Showa, Kohaku
        $this->assertCount(2, $result);
    }

    public function test_koi_without_pond_not_matched_by_relation_filter(): void
    {
        // Shusui has no pond (pond_id = null)
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                PondWaterTypeFilter::via('pond'),
            ])
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->is('fresh'))
            ->getQuery()
            ->get();

        // Should not include Shusui
        $this->assertFalse($result->pluck('name')->contains('Shusui'));
    }
}
