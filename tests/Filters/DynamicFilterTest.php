<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\BooleanFilter;
use Ameax\FilterCore\Filters\Dynamic\DynamicBooleanFilter;
use Ameax\FilterCore\Filters\Dynamic\DynamicFilter;
use Ameax\FilterCore\Filters\Dynamic\DynamicIntegerFilter;
use Ameax\FilterCore\Filters\Dynamic\DynamicSelectFilter;
use Ameax\FilterCore\Filters\Dynamic\DynamicTextFilter;
use Ameax\FilterCore\Filters\IntegerFilter;
use Ameax\FilterCore\Filters\SelectFilter;
use Ameax\FilterCore\Filters\TextFilter;
use Ameax\FilterCore\MatchModes\ContainsMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

class DynamicFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Koi::clearFilterCache();
        Pond::clearFilterCache();

        // Create ponds
        $freshPond = Pond::create(['name' => 'Fresh Pond', 'water_type' => 'fresh', 'capacity' => 5000, 'is_heated' => true]);
        $saltPond = Pond::create(['name' => 'Salt Pond', 'water_type' => 'salt', 'capacity' => 3000, 'is_heated' => false]);

        // Create kois
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'pond_id' => $saltPond->id]);
    }

    // ========================================
    // Dynamic Select Filter Tests
    // ========================================

    public function test_create_dynamic_select_filter(): void
    {
        $filter = SelectFilter::dynamic('custom_status')
            ->withColumn('status')
            ->withLabel('Custom Status')
            ->withOptions(['active' => 'Active', 'inactive' => 'Inactive']);

        $this->assertInstanceOf(DynamicSelectFilter::class, $filter);
        $this->assertInstanceOf(DynamicFilter::class, $filter);
        $this->assertEquals('custom_status', $filter->getKey());
        $this->assertEquals('status', $filter->column());
        $this->assertEquals('Custom Status', $filter->label());
        $this->assertEquals(FilterTypeEnum::SELECT, $filter->type());
        $this->assertEquals(['active' => 'Active', 'inactive' => 'Inactive'], $filter->options());
    }

    public function test_dynamic_select_filter_defaults(): void
    {
        $filter = SelectFilter::dynamic('my_key');

        $this->assertEquals('my_key', $filter->getKey());
        $this->assertEquals('my_key', $filter->column()); // defaults to key
        $this->assertEquals('my_key', $filter->label()); // defaults to key
        $this->assertEquals([], $filter->options());
        $this->assertFalse($filter->nullable());
    }

    public function test_dynamic_select_filter_allowed_modes(): void
    {
        $filter = SelectFilter::dynamic('test');
        $modes = $filter->allowedModes();

        $this->assertCount(4, $modes);
        $this->assertEquals('is', $modes[0]->key());
        $this->assertEquals('is_not', $modes[1]->key());
        $this->assertEquals('any', $modes[2]->key());
        $this->assertEquals('none', $modes[3]->key());
    }

    public function test_dynamic_select_filter_with_nullable(): void
    {
        $filter = SelectFilter::dynamic('test')->withNullable();

        $this->assertTrue($filter->nullable());
    }

    public function test_dynamic_select_filter_with_meta(): void
    {
        $filter = SelectFilter::dynamic('test')->withMeta(['custom' => 'data']);

        $this->assertEquals(['custom' => 'data'], $filter->meta());
    }

    public function test_dynamic_select_filter_with_relation(): void
    {
        $filter = SelectFilter::dynamic('pond_water')
            ->withColumn('water_type')
            ->withRelation('pond');

        $this->assertTrue($filter->hasRelation());
        $this->assertEquals('pond', $filter->getRelation());
    }

    // ========================================
    // Dynamic Integer Filter Tests
    // ========================================

    public function test_create_dynamic_integer_filter(): void
    {
        $filter = IntegerFilter::dynamic('custom_count')
            ->withColumn('count')
            ->withLabel('Custom Count');

        $this->assertInstanceOf(DynamicIntegerFilter::class, $filter);
        $this->assertInstanceOf(DynamicFilter::class, $filter);
        $this->assertEquals('custom_count', $filter->getKey());
        $this->assertEquals('count', $filter->column());
        $this->assertEquals('Custom Count', $filter->label());
        $this->assertEquals(FilterTypeEnum::INTEGER, $filter->type());
    }

    public function test_dynamic_integer_filter_allowed_modes(): void
    {
        $filter = IntegerFilter::dynamic('test');
        $modes = $filter->allowedModes();

        $this->assertCount(5, $modes);
        $this->assertEquals('is', $modes[0]->key());
        $this->assertEquals('is_not', $modes[1]->key());
        $this->assertEquals('gt', $modes[2]->key());
        $this->assertEquals('lt', $modes[3]->key());
        $this->assertEquals('between', $modes[4]->key());
    }

    // ========================================
    // Dynamic Text Filter Tests
    // ========================================

    public function test_create_dynamic_text_filter(): void
    {
        $filter = TextFilter::dynamic('custom_name')
            ->withColumn('name')
            ->withLabel('Custom Name');

        $this->assertInstanceOf(DynamicTextFilter::class, $filter);
        $this->assertInstanceOf(DynamicFilter::class, $filter);
        $this->assertEquals('custom_name', $filter->getKey());
        $this->assertEquals('name', $filter->column());
        $this->assertEquals('Custom Name', $filter->label());
        $this->assertEquals(FilterTypeEnum::TEXT, $filter->type());
    }

    public function test_dynamic_text_filter_default_mode(): void
    {
        $filter = TextFilter::dynamic('test');

        $this->assertInstanceOf(ContainsMatchMode::class, $filter->defaultMode());
    }

    public function test_dynamic_text_filter_allowed_modes(): void
    {
        $filter = TextFilter::dynamic('test');
        $modes = $filter->allowedModes();

        $this->assertCount(3, $modes);
        $this->assertEquals('contains', $modes[0]->key());
        $this->assertEquals('is', $modes[1]->key());
        $this->assertEquals('is_not', $modes[2]->key());
    }

    // ========================================
    // Dynamic Boolean Filter Tests
    // ========================================

    public function test_create_dynamic_boolean_filter(): void
    {
        $filter = BooleanFilter::dynamic('custom_active')
            ->withColumn('is_active')
            ->withLabel('Is Active');

        $this->assertInstanceOf(DynamicBooleanFilter::class, $filter);
        $this->assertInstanceOf(DynamicFilter::class, $filter);
        $this->assertEquals('custom_active', $filter->getKey());
        $this->assertEquals('is_active', $filter->column());
        $this->assertEquals('Is Active', $filter->label());
        $this->assertEquals(FilterTypeEnum::BOOLEAN, $filter->type());
    }

    public function test_dynamic_boolean_filter_allowed_modes(): void
    {
        $filter = BooleanFilter::dynamic('test');
        $modes = $filter->allowedModes();

        $this->assertCount(1, $modes);
        $this->assertEquals('is', $modes[0]->key());
    }

    // ========================================
    // Filter Definition Tests
    // ========================================

    public function test_dynamic_filter_to_definition(): void
    {
        $filter = SelectFilter::dynamic('custom_status')
            ->withColumn('status')
            ->withLabel('Custom Status')
            ->withOptions(['a' => 'A', 'b' => 'B']);

        $definition = $filter->toDefinition();

        $this->assertEquals('custom_status', $definition->getKey());
        $this->assertEquals('status', $definition->getColumn());
        $this->assertEquals('Custom Status', $definition->getLabel());
        $this->assertEquals(FilterTypeEnum::SELECT, $definition->getType());
        $this->assertEquals(['a' => 'A', 'b' => 'B'], $definition->getOptions());
    }

    public function test_dynamic_filter_resolve_key(): void
    {
        $filter = SelectFilter::dynamic('my_custom_key');

        $this->assertEquals('my_custom_key', $filter->resolveKey());
    }

    // ========================================
    // Query Applicator Integration Tests
    // ========================================

    public function test_query_applicator_with_dynamic_select_filter(): void
    {
        $filter = SelectFilter::dynamic('custom_status')->withColumn('status');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('custom_status', new IsMatchMode, 'active'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'active'));
    }

    public function test_query_applicator_with_dynamic_integer_filter(): void
    {
        $filter = IntegerFilter::dynamic('custom_count')->withColumn('count');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('custom_count', new GreaterThanMatchMode, 5))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count > 5));
    }

    public function test_query_applicator_with_dynamic_text_filter(): void
    {
        $filter = TextFilter::dynamic('custom_name')->withColumn('name');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('custom_name', new ContainsMatchMode, 'Ko'))
            ->getQuery()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }

    public function test_query_applicator_with_dynamic_boolean_filter(): void
    {
        $filter = BooleanFilter::dynamic('custom_active')->withColumn('is_active');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('custom_active', new IsMatchMode, true))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->is_active === true));
    }

    public function test_query_applicator_with_dynamic_relation_filter(): void
    {
        $filter = SelectFilter::dynamic('pond_water_type')
            ->withColumn('water_type')
            ->withRelation('pond');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$filter])
            ->applyFilter(new FilterValue('pond_water_type', new IsMatchMode, 'fresh'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_combine_dynamic_and_static_filters(): void
    {
        $dynamicFilter = SelectFilter::dynamic('custom_status')->withColumn('status');

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                $dynamicFilter,
                KoiCountFilter::class,
            ])
            ->applyFilters([
                new FilterValue('custom_status', new IsMatchMode, 'active'),
                new FilterValue('KoiCountFilter', new GreaterThanMatchMode, 15),
            ])
            ->getQuery()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }
}
