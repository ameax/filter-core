<?php

namespace Ameax\FilterCore\Tests\Models;

use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\Models\QuickFilterPreset;
use Ameax\FilterCore\Tests\TestCase;
use Carbon\Carbon;

class QuickFilterPresetTest extends TestCase
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
    // BASIC CRUD
    // =========================================================================

    public function test_can_create_preset(): void
    {
        $preset = QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('filter_quick_presets', [
            'id' => $preset->id,
            'scope' => null,
            'direction' => null,
            'is_active' => true,
        ]);
    }

    public function test_can_create_scoped_preset(): void
    {
        $preset = QuickFilterPreset::create([
            'scope' => 'invoices',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_month'],
            'direction' => 'past',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->assertEquals('invoices', $preset->scope);
        $this->assertEquals('past', $preset->direction);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function test_date_range_value_accessor(): void
    {
        $preset = QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $value = $preset->date_range_value;

        $this->assertInstanceOf(DateRangeValue::class, $value);
    }

    public function test_label_accessor_quick(): void
    {
        $preset = QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($preset->label);
    }

    public function test_label_accessor_relative(): void
    {
        $preset = QuickFilterPreset::create([
            'date_range_config' => [
                'type' => 'relative',
                'direction' => 'past',
                'amount' => 30,
                'unit' => 'day',
                'includePartial' => true,
            ],
            'direction' => 'past',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($preset->label);
    }

    // =========================================================================
    // RESOLVE
    // =========================================================================

    public function test_resolve_returns_resolved_date_range(): void
    {
        $preset = QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $resolved = $preset->resolve();

        $this->assertEquals('2024-11-15 00:00:00', $resolved->start->toDateTimeString());
        $this->assertEquals('2024-11-15 23:59:59', $resolved->end->toDateTimeString());
    }

    // =========================================================================
    // SCOPES - ACTIVE
    // =========================================================================

    public function test_scope_active(): void
    {
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'yesterday'],
            'is_active' => false,
            'sort_order' => 20,
        ]);

        $active = QuickFilterPreset::active()->get();

        $this->assertCount(1, $active);
    }

    // =========================================================================
    // SCOPES - GLOBAL
    // =========================================================================

    public function test_scope_global(): void
    {
        QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'scope' => 'invoices',
            'date_range_config' => ['type' => 'quick', 'quick' => 'yesterday'],
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $global = QuickFilterPreset::global()->get();

        $this->assertCount(1, $global);
    }

    // =========================================================================
    // SCOPES - FOR SCOPE
    // =========================================================================

    public function test_scope_for_scope_includes_global(): void
    {
        QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'scope' => 'invoices',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_month'],
            'is_active' => true,
            'sort_order' => 20,
        ]);
        QuickFilterPreset::create([
            'scope' => 'warranty',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_year'],
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $forInvoices = QuickFilterPreset::forScope('invoices')->get();

        $this->assertCount(2, $forInvoices); // global + invoices
    }

    public function test_scope_for_scopes_multiple(): void
    {
        QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'scope' => 'invoices',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_month'],
            'is_active' => true,
            'sort_order' => 20,
        ]);
        QuickFilterPreset::create([
            'scope' => 'tenant_123',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_quarter'],
            'is_active' => true,
            'sort_order' => 30,
        ]);
        QuickFilterPreset::create([
            'scope' => 'warranty',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_year'],
            'is_active' => true,
            'sort_order' => 40,
        ]);

        $forMultiple = QuickFilterPreset::forScopes(['invoices', 'tenant_123'])->get();

        $this->assertCount(3, $forMultiple); // global + invoices + tenant_123
    }

    // =========================================================================
    // SCOPES - FOR DIRECTION
    // =========================================================================

    public function test_scope_for_direction_past(): void
    {
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null, // both
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'yesterday'],
            'direction' => 'past',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'tomorrow'],
            'direction' => 'future',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $pastOnly = QuickFilterPreset::forDirection([DateDirection::PAST])->get();

        $this->assertCount(2, $pastOnly); // null (both) + past
    }

    public function test_scope_for_direction_future(): void
    {
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'yesterday'],
            'direction' => 'past',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'tomorrow'],
            'direction' => 'future',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $futureOnly = QuickFilterPreset::forDirection([DateDirection::FUTURE])->get();

        $this->assertCount(2, $futureOnly); // null (both) + future
    }

    public function test_scope_for_direction_null_returns_all(): void
    {
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'yesterday'],
            'direction' => 'past',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'tomorrow'],
            'direction' => 'future',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $all = QuickFilterPreset::forDirection(null)->get();

        $this->assertCount(3, $all);
    }

    // =========================================================================
    // SCOPES - ORDERED
    // =========================================================================

    public function test_scope_ordered(): void
    {
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_year'],
            'sort_order' => 300,
            'is_active' => true,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'sort_order' => 100,
            'is_active' => true,
        ]);
        QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_month'],
            'sort_order' => 200,
            'is_active' => true,
        ]);

        $ordered = QuickFilterPreset::ordered()->get();

        $this->assertEquals(100, $ordered[0]->sort_order);
        $this->assertEquals(200, $ordered[1]->sort_order);
        $this->assertEquals(300, $ordered[2]->sort_order);
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    public function test_get_for_filter(): void
    {
        QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);
        QuickFilterPreset::create([
            'scope' => 'invoices',
            'date_range_config' => ['type' => 'quick', 'quick' => 'this_month'],
            'direction' => 'past',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'tomorrow'],
            'direction' => 'future',
            'sort_order' => 30,
            'is_active' => false, // inactive
        ]);

        $presets = QuickFilterPreset::getForFilter(['invoices'], [DateDirection::PAST]);

        $this->assertCount(2, $presets);
    }

    public function test_get_options_for_filter(): void
    {
        QuickFilterPreset::create([
            'scope' => null,
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $options = QuickFilterPreset::getOptionsForFilter();

        $this->assertCount(1, $options);
        $this->assertArrayHasKey('id', $options[0]);
        $this->assertArrayHasKey('label', $options[0]);
        $this->assertArrayHasKey('config', $options[0]);
    }

    // =========================================================================
    // TO OPTION ARRAY
    // =========================================================================

    public function test_to_option_array(): void
    {
        $preset = QuickFilterPreset::create([
            'date_range_config' => ['type' => 'quick', 'quick' => 'today'],
            'direction' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $option = $preset->toOptionArray();

        $this->assertEquals($preset->id, $option['id']);
        $this->assertNotEmpty($option['label']);
        $this->assertEquals(['type' => 'quick', 'quick' => 'today'], $option['config']);
    }
}
