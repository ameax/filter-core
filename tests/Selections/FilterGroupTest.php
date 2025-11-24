<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Tests\Selections;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

class FilterGroupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Koi::clearFilterCache();
        Pond::clearFilterCache();

        // Create ponds
        $freshPond = Pond::create(['name' => 'Fresh Pond', 'water_type' => 'fresh', 'capacity' => 5000, 'is_heated' => true]);
        $saltPond = Pond::create(['name' => 'Salt Pond', 'water_type' => 'salt', 'capacity' => 3000, 'is_heated' => false]);
        $brackishPond = Pond::create(['name' => 'Brackish Pond', 'water_type' => 'brackish', 'capacity' => 2000, 'is_heated' => true]);

        // Create kois with various combinations
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'pond_id' => $saltPond->id]);
        Koi::create(['name' => 'Asagi', 'status' => 'pending', 'count' => 15, 'is_active' => true, 'pond_id' => $brackishPond->id]);
        Koi::create(['name' => 'Shusui', 'status' => 'pending', 'count' => 0, 'is_active' => false, 'pond_id' => null]);
    }

    // ========================================
    // FilterGroup Basic Tests
    // ========================================

    public function test_create_and_group(): void
    {
        $group = FilterGroup::and();

        $this->assertEquals(GroupOperatorEnum::AND, $group->getOperator());
        $this->assertTrue($group->isEmpty());
    }

    public function test_create_or_group(): void
    {
        $group = FilterGroup::or();

        $this->assertEquals(GroupOperatorEnum::OR, $group->getOperator());
        $this->assertTrue($group->isEmpty());
    }

    public function test_add_filter_value_to_group(): void
    {
        $group = FilterGroup::and();
        $group->where(KoiStatusFilter::class)->is('active');

        $this->assertFalse($group->isEmpty());
        $this->assertEquals(1, $group->count());
    }

    public function test_add_nested_group(): void
    {
        $group = FilterGroup::and();
        $group->where(KoiStatusFilter::class)->is('active');
        $group->orWhere(function (FilterGroup $g) {
            $g->where(KoiStatusFilter::class)->is('pending');
        });

        $this->assertEquals(2, $group->count());
        $this->assertTrue($group->hasNestedGroups());
    }

    public function test_get_all_filter_values_flattens(): void
    {
        $group = FilterGroup::and();
        $group->where(KoiStatusFilter::class)->is('active');
        $group->orWhere(function (FilterGroup $g) {
            $g->where(KoiStatusFilter::class)->is('pending');
            $g->where(KoiCountFilter::class)->gt(5);
        });

        $allValues = $group->getAllFilterValues();

        $this->assertCount(3, $allValues);
    }

    public function test_get_all_filter_keys(): void
    {
        $group = FilterGroup::and();
        $group->where(KoiStatusFilter::class)->is('active');
        $group->where(KoiCountFilter::class)->gt(5);
        $group->where(KoiStatusFilter::class)->is('pending'); // duplicate key

        $keys = $group->getAllFilterKeys();

        $this->assertCount(2, $keys); // unique keys only
        $this->assertContains('KoiStatusFilter', $keys);
        $this->assertContains('KoiCountFilter', $keys);
    }

    // ========================================
    // FilterGroup Serialization Tests
    // ========================================

    public function test_group_to_array(): void
    {
        $group = FilterGroup::and();
        $group->where(KoiStatusFilter::class)->is('active');

        $array = $group->toArray();

        $this->assertEquals('and', $array['operator']);
        $this->assertCount(1, $array['items']);
        $this->assertEquals('KoiStatusFilter', $array['items'][0]['filter']);
    }

    public function test_nested_group_to_array(): void
    {
        $group = FilterGroup::and();
        $group->where(KoiStatusFilter::class)->is('active');
        $group->orWhere(function (FilterGroup $g) {
            $g->where(KoiStatusFilter::class)->is('pending');
        });

        $array = $group->toArray();

        $this->assertEquals('and', $array['operator']);
        $this->assertCount(2, $array['items']);
        // First item is a filter value
        $this->assertArrayHasKey('filter', $array['items'][0]);
        // Second item is a nested group
        $this->assertArrayHasKey('operator', $array['items'][1]);
        $this->assertEquals('or', $array['items'][1]['operator']);
    }

    public function test_group_from_array(): void
    {
        $data = [
            'operator' => 'and',
            'items' => [
                ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
                ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 5],
            ],
        ];

        $group = FilterGroup::fromArray($data);

        $this->assertEquals(GroupOperatorEnum::AND, $group->getOperator());
        $this->assertEquals(2, $group->count());
    }

    public function test_nested_group_from_array(): void
    {
        $data = [
            'operator' => 'and',
            'items' => [
                ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
                [
                    'operator' => 'or',
                    'items' => [
                        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'pending'],
                        ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 10],
                    ],
                ],
            ],
        ];

        $group = FilterGroup::fromArray($data);

        $this->assertEquals(GroupOperatorEnum::AND, $group->getOperator());
        $this->assertEquals(2, $group->count());
        $this->assertTrue($group->hasNestedGroups());

        $items = $group->getItems();
        $this->assertInstanceOf(FilterValue::class, $items[0]);
        $this->assertInstanceOf(FilterGroup::class, $items[1]);
        $this->assertEquals(GroupOperatorEnum::OR, $items[1]->getOperator());
    }

    // ========================================
    // FilterSelection OR Logic Tests
    // ========================================

    public function test_selection_with_or_where(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('pending');
            });

        $this->assertTrue($selection->hasNestedGroups());
        $this->assertCount(2, $selection->all()); // flattened count
    }

    public function test_selection_make_or(): void
    {
        // Create an OR selection (top-level OR)
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        $this->assertEquals(GroupOperatorEnum::OR, $selection->getOperator());
        $this->assertCount(2, $selection->all());
    }

    public function test_selection_with_nested_groups_serialization(): void
    {
        $selection = FilterSelection::make('Test')
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('pending');
                $g->where(KoiCountFilter::class)->gt(10);
            });

        $array = $selection->toArray();

        // Should use new 'group' format for complex selections
        $this->assertArrayHasKey('group', $array);
        $this->assertArrayNotHasKey('filters', $array);
        $this->assertEquals('and', $array['group']['operator']);
    }

    public function test_selection_backward_compatible_serialization(): void
    {
        // Simple AND selection should use legacy format
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        $array = $selection->toArray();

        // Should use legacy 'filters' format for simple selections
        $this->assertArrayHasKey('filters', $array);
        $this->assertArrayNotHasKey('group', $array);
    }

    public function test_selection_from_array_with_group(): void
    {
        $data = [
            'name' => 'Complex Selection',
            'group' => [
                'operator' => 'and',
                'items' => [
                    ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
                    [
                        'operator' => 'or',
                        'items' => [
                            ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'pending'],
                        ],
                    ],
                ],
            ],
        ];

        $selection = FilterSelection::fromArray($data);

        $this->assertEquals('Complex Selection', $selection->getName());
        $this->assertTrue($selection->hasNestedGroups());
    }

    public function test_selection_json_round_trip_with_groups(): void
    {
        $original = FilterSelection::make('Test')
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('pending');
                $g->where(KoiCountFilter::class)->gt(10);
            });

        $json = $original->toJson();
        $restored = FilterSelection::fromJson($json);

        $this->assertEquals($original->getName(), $restored->getName());
        $this->assertEquals($original->hasNestedGroups(), $restored->hasNestedGroups());
        $this->assertEquals(count($original->all()), count($restored->all()));
    }

    // ========================================
    // Query Application Tests - OR Logic
    // ========================================

    public function test_or_selection_returns_union_of_results(): void
    {
        // status = 'active' OR status = 'pending'
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        $result = Koi::query()->applySelection($selection)->get();

        // Showa (active), Kohaku (active), Asagi (pending), Shusui (pending)
        $this->assertCount(4, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa', 'Shusui'], $names);
    }

    public function test_and_with_nested_or_group(): void
    {
        // count > 5 AND (status = 'active' OR status = 'pending')
        $selection = FilterSelection::make()
            ->where(KoiCountFilter::class)->gt(5)
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('active');
                $g->where(KoiStatusFilter::class)->is('pending');
            });

        $result = Koi::query()->applySelection($selection)->get();

        // count > 5: Showa (10), Kohaku (20), Asagi (15)
        // AND (active OR pending): Showa (active), Kohaku (active), Asagi (pending)
        // Result: Showa, Kohaku, Asagi
        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa'], $names);
    }

    public function test_complex_nested_groups(): void
    {
        // (status = 'active' AND count > 15) OR (status = 'pending')
        $selection = FilterSelection::makeOr()
            ->andWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('active');
                $g->where(KoiCountFilter::class)->gt(15);
            })
            ->andWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('pending');
            });

        $result = Koi::query()->applySelection($selection)->get();

        // (active AND count > 15): Kohaku (20)
        // OR pending: Asagi, Shusui
        // Result: Kohaku, Asagi, Shusui
        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Shusui'], $names);
    }

    public function test_deeply_nested_groups(): void
    {
        // (status = 'active' AND (count > 15 OR count < 5)) OR status = 'inactive'
        $selection = FilterSelection::makeOr()
            ->andWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('active');
                $g->orWhere(function (FilterGroup $inner) {
                    $inner->where(KoiCountFilter::class)->gt(15);
                    $inner->where(KoiCountFilter::class)->lt(5);
                });
            })
            ->andWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('inactive');
            });

        $result = Koi::query()->applySelection($selection)->get();

        // (active AND (count > 15 OR count < 5)): Kohaku (20, active)
        // OR inactive: Sanke
        // Result: Kohaku, Sanke
        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Kohaku', 'Sanke'], $names);
    }

    public function test_or_with_relation_filters(): void
    {
        // water_type = 'fresh' OR water_type = 'brackish'
        $selection = FilterSelection::makeOr()
            ->where(PondWaterTypeFilter::class)->is('fresh')
            ->where(PondWaterTypeFilter::class)->is('brackish');

        $result = Koi::query()->applySelection($selection)->get();

        // fresh pond: Showa, Kohaku
        // brackish pond: Asagi
        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa'], $names);
    }

    public function test_mixed_direct_and_relation_filters_with_or(): void
    {
        // status = 'pending' OR water_type = 'fresh'
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('pending')
            ->where(PondWaterTypeFilter::class)->is('fresh');

        $result = Koi::query()->applySelection($selection)->get();

        // pending: Asagi, Shusui
        // fresh pond: Showa, Kohaku
        // Result: Asagi, Shusui, Showa, Kohaku
        $this->assertCount(4, $result);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function test_empty_or_group_is_ignored(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function (FilterGroup $g) {
                // Empty group - should be ignored
            });

        // Should not have nested groups since empty group was ignored
        $this->assertFalse($selection->hasNestedGroups());
    }

    public function test_single_item_or_group(): void
    {
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active');

        $result = Koi::query()->applySelection($selection)->get();

        // Just active: Showa, Kohaku
        $this->assertCount(2, $result);
    }

    public function test_triple_nested_groups(): void
    {
        // ((status = 'active' AND count > 10) OR status = 'inactive') AND pond exists
        $selection = FilterSelection::make()
            ->orWhere(function (FilterGroup $or) {
                $or->andWhere(function (FilterGroup $and) {
                    $and->where(KoiStatusFilter::class)->is('active');
                    $and->where(KoiCountFilter::class)->gt(10);
                });
                $or->andWhere(function (FilterGroup $and) {
                    $and->where(KoiStatusFilter::class)->is('inactive');
                });
            })
            ->where(PondWaterTypeFilter::class)->any(['fresh', 'salt', 'brackish']);

        $result = Koi::query()->applySelection($selection)->get();

        // (active AND count > 10): Kohaku (20)
        // OR inactive: Sanke
        // AND has pond: Kohaku, Sanke (both have ponds)
        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Kohaku', 'Sanke'], $names);
    }
}
