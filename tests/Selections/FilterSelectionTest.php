<?php

namespace Ameax\FilterCore\Tests\Selections;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

class FilterSelectionTest extends TestCase
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
    // Basic Creation Tests
    // ========================================

    public function test_create_empty_selection(): void
    {
        $selection = FilterSelection::make();

        $this->assertFalse($selection->hasFilters());
        $this->assertEquals(0, $selection->count());
        $this->assertEmpty($selection->all());
    }

    public function test_create_selection_with_name(): void
    {
        $selection = FilterSelection::make('Active Kois');

        $this->assertEquals('Active Kois', $selection->getName());
    }

    public function test_set_name_and_description(): void
    {
        $selection = FilterSelection::make()
            ->name('My Selection')
            ->description('A test selection');

        $this->assertEquals('My Selection', $selection->getName());
        $this->assertEquals('A test selection', $selection->getDescription());
    }

    // ========================================
    // Add Filter Tests
    // ========================================

    public function test_add_filter_value(): void
    {
        $selection = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'));

        $this->assertTrue($selection->hasFilters());
        $this->assertEquals(1, $selection->count());
    }

    public function test_add_multiple_filter_values(): void
    {
        $selection = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->add(FilterValue::for(KoiCountFilter::class)->greaterThan(5));

        $this->assertEquals(2, $selection->count());
    }

    // ========================================
    // Fluent Where Syntax Tests
    // ========================================

    public function test_where_fluent_syntax(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->greaterThan(5);

        $this->assertEquals(2, $selection->count());
        $this->assertTrue($selection->has(KoiStatusFilter::class));
        $this->assertTrue($selection->has(KoiCountFilter::class));
    }

    public function test_where_returns_selection_for_chaining(): void
    {
        $result = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active');

        $this->assertInstanceOf(FilterSelection::class, $result);
    }

    // ========================================
    // Has and Get Tests
    // ========================================

    public function test_has_returns_true_for_existing_filter(): void
    {
        $selection = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'));

        $this->assertTrue($selection->has(KoiStatusFilter::class));
        $this->assertFalse($selection->has(KoiCountFilter::class));
    }

    public function test_get_returns_filter_value(): void
    {
        $selection = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'));

        $filterValue = $selection->get(KoiStatusFilter::class);

        $this->assertNotNull($filterValue);
        $this->assertEquals('active', $filterValue->getValue());
        $this->assertInstanceOf(IsMatchMode::class, $filterValue->getMatchMode());
    }

    public function test_get_returns_null_for_missing_filter(): void
    {
        $selection = FilterSelection::make();

        $this->assertNull($selection->get(KoiStatusFilter::class));
    }

    // ========================================
    // Remove and Clear Tests
    // ========================================

    public function test_remove_filter(): void
    {
        $selection = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->add(FilterValue::for(KoiCountFilter::class)->greaterThan(5));

        $selection->remove(KoiStatusFilter::class);

        $this->assertEquals(1, $selection->count());
        $this->assertFalse($selection->has(KoiStatusFilter::class));
        $this->assertTrue($selection->has(KoiCountFilter::class));
    }

    public function test_clear_removes_all_filters(): void
    {
        $selection = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->add(FilterValue::for(KoiCountFilter::class)->greaterThan(5));

        $selection->clear();

        $this->assertEquals(0, $selection->count());
        $this->assertFalse($selection->hasFilters());
    }

    // ========================================
    // JSON Serialization Tests
    // ========================================

    public function test_to_json(): void
    {
        $selection = FilterSelection::make('Test Selection')
            ->description('A test')
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'));

        $json = $selection->toJson();
        $data = json_decode($json, true);

        $this->assertEquals('Test Selection', $data['name']);
        $this->assertEquals('A test', $data['description']);
        $this->assertCount(1, $data['filters']);
        $this->assertEquals('KoiStatusFilter', $data['filters'][0]['filter']);
        $this->assertEquals('is', $data['filters'][0]['mode']);
        $this->assertEquals('active', $data['filters'][0]['value']);
    }

    public function test_from_json(): void
    {
        $json = json_encode([
            'name' => 'Loaded Selection',
            'description' => 'From JSON',
            'filters' => [
                ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
                ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 10],
            ],
        ]);

        $selection = FilterSelection::fromJson($json);

        $this->assertEquals('Loaded Selection', $selection->getName());
        $this->assertEquals('From JSON', $selection->getDescription());
        $this->assertEquals(2, $selection->count());
    }

    public function test_round_trip_json(): void
    {
        $original = FilterSelection::make('Round Trip')
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->between(5, 15);

        $json = $original->toJson();
        $restored = FilterSelection::fromJson($json);

        $this->assertEquals($original->getName(), $restored->getName());
        $this->assertEquals($original->count(), $restored->count());
        $this->assertEquals(
            $original->get(KoiStatusFilter::class)?->getValue(),
            $restored->get(KoiStatusFilter::class)?->getValue()
        );
    }

    public function test_to_array(): void
    {
        $selection = FilterSelection::make('Array Test')
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'));

        $array = $selection->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('filters', $array);
    }

    // ========================================
    // Integration with Filterable Trait
    // ========================================

    public function test_apply_selection_to_query(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active');

        $result = Koi::query()->applyFilters($selection)->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'active'));
    }

    public function test_apply_selection_with_multiple_filters(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->greaterThan(5);

        $result = Koi::query()->applyFilters($selection)->get();

        $this->assertCount(2, $result);
    }

    public function test_apply_selection_with_relation_filter(): void
    {
        $selection = FilterSelection::make()
            ->where(PondWaterTypeFilter::class)->is('fresh');

        $result = Koi::query()->applyFilters($selection)->get();

        $this->assertCount(2, $result);
    }

    public function test_save_and_load_selection(): void
    {
        // Create and save
        $selection = FilterSelection::make('Saved Filter')
            ->description('My saved filter configuration')
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->greaterThan(10);

        $json = $selection->toJson();

        // Simulate loading from database
        $loaded = FilterSelection::fromJson($json);

        // Apply loaded selection
        $result = Koi::query()->applyFilters($loaded)->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Kohaku', $result->first()->name);
    }
}
