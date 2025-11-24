<?php

namespace Ameax\FilterCore\Tests\Models;

use Ameax\FilterCore\Models\FilterPreset;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FilterPresetTest extends TestCase
{
    protected function createKoiTestData(): void
    {
        // Clear existing data
        if (\Schema::hasTable('kois')) {
            Koi::query()->delete();
        }
        FilterPreset::query()->delete();

        // Create test data
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10]);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20]);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5]);
    }

    // ========================================
    // fromSelection() Tests
    // ========================================

    public function test_creates_preset_from_selection_with_model(): void
    {
        $selection = FilterSelection::make('Active Kois', Koi::class)
            ->description('All active kois')
            ->where(KoiStatusFilter::class)->is('active');

        $preset = FilterPreset::fromSelection($selection);

        $this->assertEquals('Active Kois', $preset->name);
        $this->assertEquals('All active kois', $preset->description);
        $this->assertEquals(Koi::class, $preset->model_type);
        $this->assertFalse($preset->is_public);
        $this->assertNull($preset->user_id);
        $this->assertIsArray($preset->configuration);
    }

    public function test_creates_preset_with_explicit_model_type(): void
    {
        $selection = FilterSelection::make('Active Items')
            ->where(KoiStatusFilter::class)->is('active');

        $preset = FilterPreset::fromSelection($selection, Koi::class);

        $this->assertEquals(Koi::class, $preset->model_type);
    }

    public function test_creates_preset_with_user_id(): void
    {
        $selection = FilterSelection::make('My Filter', Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $preset = FilterPreset::fromSelection($selection, null, 123);

        $this->assertEquals(123, $preset->user_id);
    }

    public function test_creates_public_preset(): void
    {
        $selection = FilterSelection::make('Public Filter', Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $preset = FilterPreset::fromSelection($selection, null, null, true);

        $this->assertTrue($preset->is_public);
    }

    public function test_throws_exception_when_no_model_type_provided(): void
    {
        $selection = FilterSelection::make('Filter Without Model')
            ->where(KoiStatusFilter::class)->is('active');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FilterPreset requires a model type');

        FilterPreset::fromSelection($selection);
    }

    public function test_uses_default_name_when_selection_has_no_name(): void
    {
        $selection = FilterSelection::make()
            ->forModel(Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $preset = FilterPreset::fromSelection($selection);

        $this->assertEquals('Untitled Filter', $preset->name);
    }

    // ========================================
    // toSelection() Tests
    // ========================================

    public function test_converts_preset_to_selection(): void
    {
        $preset = FilterPreset::create([
            'name' => 'Test Preset',
            'description' => 'Test Description',
            'model_type' => Koi::class,
            'configuration' => [
                'model' => Koi::class,
                'filters' => [
                    ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
                ],
            ],
        ]);

        $selection = $preset->toSelection();

        $this->assertInstanceOf(FilterSelection::class, $selection);
        $this->assertEquals('Test Preset', $selection->getName());
        $this->assertEquals('Test Description', $selection->getDescription());
        $this->assertEquals(Koi::class, $selection->getModelClass());
        $this->assertEquals(1, $selection->count());
    }

    public function test_selection_can_execute_directly(): void
    {
        $this->createKoiTestData();

        $preset = FilterPreset::create([
            'name' => 'Active Kois',
            'model_type' => Koi::class,
            'configuration' => FilterSelection::make('', Koi::class)
                ->where(KoiStatusFilter::class)->is('active')
                ->toArray(),
        ]);

        $results = $preset->toSelection()->execute();

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($koi) => $koi->status === 'active'));
    }

    public function test_selection_fallback_to_preset_name(): void
    {
        $preset = FilterPreset::create([
            'name' => 'Preset Name',
            'model_type' => Koi::class,
            'configuration' => [
                'filters' => [],
            ],
        ]);

        $selection = $preset->toSelection();

        $this->assertEquals('Preset Name', $selection->getName());
    }

    public function test_selection_fallback_to_preset_model(): void
    {
        $preset = FilterPreset::create([
            'name' => 'Test',
            'model_type' => Koi::class,
            'configuration' => [
                'filters' => [],
            ],
        ]);

        $selection = $preset->toSelection();

        $this->assertEquals(Koi::class, $selection->getModelClass());
    }

    // ========================================
    // Round-Trip Tests
    // ========================================

    public function test_round_trip_serialization(): void
    {
        $original = FilterSelection::make('Round Trip Test', Koi::class)
            ->description('Test round trip')
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        $preset = FilterPreset::fromSelection($original);
        $restored = $preset->toSelection();

        $this->assertEquals($original->getName(), $restored->getName());
        $this->assertEquals($original->getDescription(), $restored->getDescription());
        $this->assertEquals($original->getModelClass(), $restored->getModelClass());
        $this->assertEquals($original->count(), $restored->count());
    }

    public function test_round_trip_produces_same_results(): void
    {
        $this->createKoiTestData();

        $original = FilterSelection::make('Test', Koi::class)
            ->where(KoiStatusFilter::class)->is('active');

        $directResults = $original->execute();

        $preset = FilterPreset::fromSelection($original);
        $restored = $preset->toSelection();
        $restoredResults = $restored->execute();

        $this->assertEquals(
            $directResults->pluck('id')->sort()->values()->all(),
            $restoredResults->pluck('id')->sort()->values()->all()
        );
    }

    // ========================================
    // Scope Tests
    // ========================================

    public function test_scope_for_model(): void
    {
        FilterPreset::create([
            'name' => 'Koi Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
        ]);

        FilterPreset::create([
            'name' => 'User Filter',
            'model_type' => 'App\Models\User',
            'configuration' => ['filters' => []],
        ]);

        $koiPresets = FilterPreset::forModel(Koi::class)->get();

        $this->assertCount(1, $koiPresets);
        $this->assertEquals('Koi Filter', $koiPresets->first()->name);
    }

    public function test_scope_for_user(): void
    {
        FilterPreset::create([
            'name' => 'User 1 Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 1,
        ]);

        FilterPreset::create([
            'name' => 'User 2 Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 2,
        ]);

        $user1Presets = FilterPreset::forUser(1)->get();

        $this->assertCount(1, $user1Presets);
        $this->assertEquals('User 1 Filter', $user1Presets->first()->name);
    }

    public function test_scope_public(): void
    {
        FilterPreset::create([
            'name' => 'Public Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'is_public' => true,
        ]);

        FilterPreset::create([
            'name' => 'Private Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'is_public' => false,
        ]);

        $publicPresets = FilterPreset::public()->get();

        $this->assertCount(1, $publicPresets);
        $this->assertEquals('Public Filter', $publicPresets->first()->name);
    }

    public function test_scope_accessible_by(): void
    {
        // User's own preset
        FilterPreset::create([
            'name' => 'My Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 1,
            'is_public' => false,
        ]);

        // Public preset
        FilterPreset::create([
            'name' => 'Public Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 2,
            'is_public' => true,
        ]);

        // Other user's private preset
        FilterPreset::create([
            'name' => 'Other Private Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 2,
            'is_public' => false,
        ]);

        $accessiblePresets = FilterPreset::accessibleBy(1)->get();

        $this->assertCount(2, $accessiblePresets);
        $this->assertTrue($accessiblePresets->pluck('name')->contains('My Filter'));
        $this->assertTrue($accessiblePresets->pluck('name')->contains('Public Filter'));
        $this->assertFalse($accessiblePresets->pluck('name')->contains('Other Private Filter'));
    }

    public function test_combined_scopes(): void
    {
        FilterPreset::create([
            'name' => 'Target Filter',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 1,
            'is_public' => false,
        ]);

        FilterPreset::create([
            'name' => 'Wrong Model',
            'model_type' => 'App\Models\User',
            'configuration' => ['filters' => []],
            'user_id' => 1,
        ]);

        FilterPreset::create([
            'name' => 'Wrong User',
            'model_type' => Koi::class,
            'configuration' => ['filters' => []],
            'user_id' => 2,
        ]);

        $presets = FilterPreset::forModel(Koi::class)->forUser(1)->get();

        $this->assertCount(1, $presets);
        $this->assertEquals('Target Filter', $presets->first()->name);
    }

    // ========================================
    // Real-World Usage Tests
    // ========================================

    public function test_save_and_load_workflow(): void
    {
        $this->createKoiTestData();

        // 1. Create and save selection
        $selection = FilterSelection::make('Active High Count', Koi::class)
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(15);

        $preset = FilterPreset::fromSelection($selection, null, 123);

        // 2. Load later
        $loadedPreset = FilterPreset::forModel(Koi::class)
            ->forUser(123)
            ->where('name', 'Active High Count')
            ->first();

        // 3. Execute
        $results = $loadedPreset->toSelection()->execute();

        $this->assertCount(1, $results);
        $this->assertEquals('Kohaku', $results->first()->name);
    }

    public function test_list_available_presets(): void
    {
        $this->createKoiTestData();

        // Create various presets
        FilterPreset::fromSelection(
            FilterSelection::make('My Filter 1', Koi::class)->where(KoiStatusFilter::class)->is('active'),
            null,
            123
        );

        FilterPreset::fromSelection(
            FilterSelection::make('My Filter 2', Koi::class)->where(KoiCountFilter::class)->gt(5),
            null,
            123
        );

        FilterPreset::fromSelection(
            FilterSelection::make('Public Filter', Koi::class)->where(KoiStatusFilter::class)->is('inactive'),
            null,
            999,
            true
        );

        // List available presets for user 123
        $presets = FilterPreset::forModel(Koi::class)
            ->accessibleBy(123)
            ->orderBy('name')
            ->get();

        $this->assertCount(3, $presets);
        $this->assertEquals(['My Filter 1', 'My Filter 2', 'Public Filter'], $presets->pluck('name')->all());
    }
}
