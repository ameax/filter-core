<?php

namespace Ameax\FilterCore\Tests\Tutorial;

use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Filters\IntegerFilter;
use Ameax\FilterCore\Filters\SelectFilter;
use Ameax\FilterCore\MatchModes\BetweenMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiActiveFilter;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondCapacityFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

/**
 * ============================================================================
 * FILTER-CORE TUTORIAL
 * ============================================================================
 *
 * This test file serves as a tutorial for understanding the filter-core system.
 * Each section builds upon the previous one, introducing new concepts.
 *
 * CONCEPTS COVERED:
 * 1. Basic Filtering - Simple filter application
 * 2. Filter Syntax Variants - Different ways to achieve the same result
 * 3. Match Modes - IS, ANY, BETWEEN, CONTAINS, etc.
 * 4. Filter Classes - Static vs Dynamic filters
 * 5. Relation Filters - Filtering through relationships
 * 6. Filter Selections - Grouping and persisting filters
 * 7. Serialization - JSON import/export
 * 8. Complete Workflow - Real-world usage patterns
 * 9. Sanitization & Validation - Automatic value processing
 * 10. Type-Safe Values - Strict typing with typedValue()
 * 11. Custom Match Modes - Extensibility via MatchModeContract
 * 12. OR Logic & FilterGroups - Complex nested AND/OR conditions
 *
 * DATA MODEL:
 * - Pond: has water_type (fresh/salt/brackish), capacity, is_heated
 * - Koi: belongs to Pond, has status (active/inactive/pending), count, is_active
 */
class TutorialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Koi::clearFilterCache();
        Pond::clearFilterCache();

        // ===========================================
        // TEST DATA SETUP
        // ===========================================
        // Create 3 ponds with different characteristics
        $freshPond = Pond::create([
            'name' => 'Fresh Pond',
            'water_type' => 'fresh',
            'capacity' => 5000,
            'is_heated' => true,
        ]);

        $saltPond = Pond::create([
            'name' => 'Salt Pond',
            'water_type' => 'salt',
            'capacity' => 3000,
            'is_heated' => false,
        ]);

        $brackishPond = Pond::create([
            'name' => 'Brackish Pond',
            'water_type' => 'brackish',
            'capacity' => 2000,
            'is_heated' => true,
        ]);

        // Create 5 kois distributed across ponds
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'pond_id' => $freshPond->id]);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'pond_id' => $saltPond->id]);
        Koi::create(['name' => 'Asagi', 'status' => 'pending', 'count' => 15, 'is_active' => true, 'pond_id' => $brackishPond->id]);
        Koi::create(['name' => 'Shusui', 'status' => 'pending', 'count' => 0, 'is_active' => false, 'pond_id' => null]); // No pond!
    }

    // ========================================================================
    // SECTION 1: BASIC FILTERING
    // ========================================================================
    // Learn how to apply simple filters to queries.
    // ========================================================================

    /**
     * The most basic way to filter: using FilterValue directly with QueryApplicator.
     *
     * QueryApplicator is the core engine that applies filters to queries.
     * It needs:
     * 1. A query to modify
     * 2. Filter definitions (what filters are available)
     * 3. Filter values (what values to filter by)
     */
    public function test_1_1_basic_filter_with_query_applicator(): void
    {
        // Step 1: Start with an Eloquent query
        $query = Koi::query();

        // Step 2: Create a QueryApplicator
        // Step 3: Register available filters with withFilters()
        // Step 4: Apply filter values with applyFilter()
        $result = QueryApplicator::for($query)
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(new FilterValue('KoiStatusFilter', new IsMatchMode, 'active'))
            ->getQuery()
            ->get();

        // Result: Only active kois (Showa, Kohaku)
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * Simpler syntax: Use the Filterable trait's scope methods.
     *
     * When a model uses the Filterable trait, you can use:
     * - applyFilter() for single filters
     * - applyFilters() for multiple filters
     *
     * The trait automatically knows which filters are available for the model.
     */
    public function test_1_2_basic_filter_with_filterable_trait(): void
    {
        // The Koi model uses the Filterable trait and defines its filters
        // in the filterResolver() method. This is much cleaner!
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        // Same result as above
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    // ========================================================================
    // SECTION 2: FILTER VALUE SYNTAX VARIANTS
    // ========================================================================
    // There are multiple ways to create FilterValue objects.
    // All produce the same result - choose based on readability.
    // ========================================================================

    /**
     * Syntax Variant A: Constructor with explicit parameters.
     *
     * Most verbose but shows exactly what's happening.
     * Use when you need full control or dynamic values.
     */
    public function test_2_1_syntax_variant_constructor(): void
    {
        $filterValue = new FilterValue(
            filterKey: 'KoiStatusFilter',
            matchMode: new IsMatchMode,
            value: 'active'
        );

        $result = Koi::query()->applyFilter($filterValue)->get();

        $this->assertCount(2, $result);
    }

    /**
     * Syntax Variant B: Fluent builder with explicit mode.
     *
     * More readable, uses the filter class directly.
     * The filter key is derived from the class name automatically.
     */
    public function test_2_2_syntax_variant_fluent_with_mode(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)
            ->mode(new IsMatchMode)
            ->value('active');

        $result = Koi::query()->applyFilter($filterValue)->get();

        $this->assertCount(2, $result);
    }

    /**
     * Syntax Variant C: Fluent builder with shorthand methods.
     *
     * More readable! Shorthand methods like is(), any(), gt()
     * automatically set both the mode and value.
     */
    public function test_2_3_syntax_variant_fluent_shorthand(): void
    {
        $filterValue = FilterValue::for(KoiStatusFilter::class)->is('active');

        $result = Koi::query()->applyFilter($filterValue)->get();

        $this->assertCount(2, $result);
    }

    /**
     * Syntax Variant D: Direct from Filter class (shortest!).
     *
     * The Filter::value() method returns a FilterValueBuilder.
     * This is the most concise and readable syntax.
     */
    public function test_2_3b_syntax_variant_filter_value(): void
    {
        // Shortest syntax: Filter::value()->matchMode()
        $filterValue = KoiStatusFilter::value()->is('active');

        $result = Koi::query()->applyFilter($filterValue)->get();

        $this->assertCount(2, $result);
    }

    /**
     * Comparison: All four variants produce identical results.
     */
    public function test_2_4_all_syntax_variants_produce_same_result(): void
    {
        // Variant A: Constructor (most verbose)
        $variantA = new FilterValue('KoiStatusFilter', new IsMatchMode, 'active');

        // Variant B: Fluent with explicit mode
        $variantB = FilterValue::for(KoiStatusFilter::class)->mode(new IsMatchMode)->value('active');

        // Variant C: Fluent shorthand
        $variantC = FilterValue::for(KoiStatusFilter::class)->is('active');

        // Variant D: Direct from Filter class (shortest!)
        $variantD = KoiStatusFilter::value()->is('active');

        // All have the same properties
        $this->assertEquals($variantA->getFilterKey(), $variantB->getFilterKey());
        $this->assertEquals($variantB->getFilterKey(), $variantC->getFilterKey());
        $this->assertEquals($variantC->getFilterKey(), $variantD->getFilterKey());

        $this->assertEquals($variantA->getMatchMode(), $variantB->getMatchMode());
        $this->assertEquals($variantB->getMatchMode(), $variantC->getMatchMode());
        $this->assertEquals($variantC->getMatchMode(), $variantD->getMatchMode());

        $this->assertEquals($variantA->getValue(), $variantB->getValue());
        $this->assertEquals($variantB->getValue(), $variantC->getValue());
        $this->assertEquals($variantC->getValue(), $variantD->getValue());

        // All produce the same query result
        $resultA = Koi::query()->applyFilter($variantA)->get();
        $resultB = Koi::query()->applyFilter($variantB)->get();
        $resultC = Koi::query()->applyFilter($variantC)->get();
        $resultD = Koi::query()->applyFilter($variantD)->get();

        $this->assertEquals($resultA->pluck('id')->all(), $resultB->pluck('id')->all());
        $this->assertEquals($resultB->pluck('id')->all(), $resultC->pluck('id')->all());
        $this->assertEquals($resultC->pluck('id')->all(), $resultD->pluck('id')->all());
    }

    // ========================================================================
    // SECTION 3: MATCH MODES
    // ========================================================================
    // Different match modes for different comparison types.
    // ========================================================================

    /**
     * IS / IS_NOT: Exact value matching.
     */
    public function test_3_1_match_mode_is_and_is_not(): void
    {
        // IS: Find kois with status = 'active'
        $active = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();
        $this->assertCount(2, $active);

        // IS_NOT: Find kois with status != 'active'
        $notActive = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->isNot('active'))
            ->get();
        $this->assertCount(3, $notActive); // inactive + 2 pending
    }

    /**
     * ANY / NONE: Multiple value matching.
     */
    public function test_3_2_match_mode_any_and_none(): void
    {
        // ANY: Find kois where status IN ('active', 'pending')
        $activeOrPending = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->any(['active', 'pending']))
            ->get();
        $this->assertCount(4, $activeOrPending);

        // NONE: Find kois where status NOT IN ('active', 'pending')
        $notActiveOrPending = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->none(['active', 'pending']))
            ->get();
        $this->assertCount(1, $notActiveOrPending); // Only Sanke (inactive)
    }

    /**
     * GREATER_THAN / LESS_THAN / BETWEEN: Numeric comparisons.
     */
    public function test_3_3_match_mode_numeric_comparisons(): void
    {
        // GREATER_THAN: count > 10
        $moreThan10 = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->gt(10))
            ->get();
        $this->assertCount(2, $moreThan10); // Kohaku (20), Asagi (15)

        // LESS_THAN: count < 10
        $lessThan10 = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->lt(10))
            ->get();
        $this->assertCount(2, $lessThan10); // Sanke (5), Shusui (0)

        // BETWEEN: 5 <= count <= 15
        $between5and15 = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->between(5, 15))
            ->get();
        $this->assertCount(3, $between5and15); // Showa (10), Sanke (5), Asagi (15)
    }

    // ========================================================================
    // SECTION 4: STATIC VS DYNAMIC FILTERS
    // ========================================================================
    // Static filters are defined as classes. Dynamic filters are created at runtime.
    // ========================================================================

    /**
     * Static Filter: Defined as a class extending SelectFilter/IntegerFilter/etc.
     *
     * Advantages:
     * - Type-safe, IDE autocompletion
     * - Reusable across the application
     * - Can have custom logic
     *
     * @see \Ameax\FilterCore\Tests\Filters\KoiStatusFilter
     */
    public function test_4_1_static_filter_class(): void
    {
        // KoiStatusFilter is a class that extends SelectFilter
        // It defines: column(), options(), and is registered in the Koi model
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        $this->assertCount(2, $result);
    }

    /**
     * Dynamic Filter: Created at runtime with configuration.
     *
     * Advantages:
     * - No class file needed
     * - Configurable at runtime
     * - Good for generated/dynamic UIs
     *
     * Use SelectFilter::dynamic(), IntegerFilter::dynamic(), etc.
     */
    public function test_4_2_dynamic_filter(): void
    {
        // Create a dynamic filter that does the same as KoiStatusFilter
        $dynamicStatusFilter = SelectFilter::dynamic('dynamic_status')
            ->withColumn('status')
            ->withLabel('Koi Status')
            ->withOptions(['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending']);

        // Apply it via QueryApplicator (need to register it first)
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$dynamicStatusFilter])
            ->applyFilter(new FilterValue('dynamic_status', new IsMatchMode, 'active'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
    }

    /**
     * Comparison: Static and Dynamic filters produce identical results.
     */
    public function test_4_3_static_and_dynamic_filters_same_result(): void
    {
        // Static filter (class-based)
        $staticResult = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        // Dynamic filter (runtime-configured)
        $dynamicFilter = SelectFilter::dynamic('status_filter')->withColumn('status');
        $dynamicResult = QueryApplicator::for(Koi::query())
            ->withFilters([$dynamicFilter])
            ->applyFilter(new FilterValue('status_filter', new IsMatchMode, 'active'))
            ->getQuery()
            ->get();

        // Same results
        $this->assertEquals(
            $staticResult->pluck('id')->sort()->values()->all(),
            $dynamicResult->pluck('id')->sort()->values()->all()
        );
    }

    /**
     * Dynamic filters with different types.
     */
    public function test_4_4_dynamic_filter_types(): void
    {
        // Dynamic SELECT filter
        $selectFilter = SelectFilter::dynamic('status')->withColumn('status');

        // Dynamic INTEGER filter
        $integerFilter = IntegerFilter::dynamic('count')->withColumn('count');

        // Combine them
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$selectFilter, $integerFilter])
            ->applyFilters([
                new FilterValue('status', new IsMatchMode, 'active'),
                new FilterValue('count', new GreaterThanMatchMode, 5),
            ])
            ->getQuery()
            ->get();

        // Active kois with count > 5: Showa (10), Kohaku (20)
        $this->assertCount(2, $result);
    }

    // ========================================================================
    // SECTION 5: RELATION FILTERS
    // ========================================================================
    // Filter through model relationships using whereHas().
    // ========================================================================

    /**
     * Relation Filter: Filter Koi by properties of their related Pond.
     *
     * The filter is defined with via('relation') to specify the relationship.
     * Internally, this uses Eloquent's whereHas().
     */
    public function test_5_1_basic_relation_filter(): void
    {
        // Find all kois in fresh water ponds
        // PondWaterTypeFilter is registered in Koi with: PondWaterTypeFilter::via('pond')
        $result = Koi::query()
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->is('fresh'))
            ->get();

        // Showa and Kohaku are in the fresh pond
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * Relation filter with numeric comparison.
     */
    public function test_5_2_relation_filter_numeric(): void
    {
        // Find kois in ponds with capacity > 4000
        $result = Koi::query()
            ->applyFilter(FilterValue::for(PondCapacityFilter::class)->gt(4000))
            ->get();

        // Only Fresh Pond has capacity > 4000
        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    /**
     * Records without relation are NOT matched by relation filters.
     *
     * Important: Kois without a pond (pond_id = null) will not be included
     * in relation filter results, because whereHas() requires the relation to exist.
     */
    public function test_5_3_relation_filter_excludes_null_relations(): void
    {
        // Shusui has no pond (pond_id = null)
        $shusui = Koi::where('name', 'Shusui')->first();
        $this->assertNull($shusui->pond_id);

        // When filtering by any pond water type, Shusui is never included
        $allWaterTypes = ['fresh', 'salt', 'brackish'];
        $result = Koi::query()
            ->applyFilter(FilterValue::for(PondWaterTypeFilter::class)->any($allWaterTypes))
            ->get();

        // 4 kois have ponds, Shusui doesn't
        $this->assertCount(4, $result);
        $this->assertFalse($result->contains('name', 'Shusui'));
    }

    /**
     * Dynamic relation filter: Configure relation at runtime.
     */
    public function test_5_4_dynamic_relation_filter(): void
    {
        // Create a dynamic filter for pond water type
        $dynamicPondFilter = SelectFilter::dynamic('pond_water')
            ->withColumn('water_type')
            ->withRelation('pond')  // Specify the relation
            ->withOptions(['fresh' => 'Fresh', 'salt' => 'Salt']);

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$dynamicPondFilter])
            ->applyFilter(new FilterValue('pond_water', new IsMatchMode, 'fresh'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
    }

    /**
     * Combining direct and relation filters.
     */
    public function test_5_5_combined_direct_and_relation_filters(): void
    {
        // Find active kois in fresh water ponds
        $result = Koi::query()
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->is('active'),      // Direct filter
                FilterValue::for(PondWaterTypeFilter::class)->is('fresh'),   // Relation filter
            ])
            ->get();

        // Both Showa and Kohaku are active AND in the fresh pond
        $this->assertCount(2, $result);
    }

    // ========================================================================
    // SECTION 6: FILTER SELECTIONS
    // ========================================================================
    // Group multiple filters into a reusable, serializable selection.
    // ========================================================================

    /**
     * FilterSelection: A container for multiple filter values.
     *
     * Use cases:
     * - Save filter presets to database
     * - Share filter configurations
     * - Build complex filter UIs
     */
    public function test_6_1_basic_filter_selection(): void
    {
        // Create a selection with multiple filters
        $selection = FilterSelection::make('Active Kois in Fresh Water')
            ->description('Finds active kois in fresh water ponds')
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->add(FilterValue::for(PondWaterTypeFilter::class)->is('fresh'));

        // Apply the entire selection to a query
        $result = Koi::query()->applyFilters($selection)->get();

        $this->assertCount(2, $result);
        $this->assertEquals('Active Kois in Fresh Water', $selection->getName());
    }

    /**
     * Fluent where() syntax for selections.
     *
     * Instead of add(), you can use where() for a more readable syntax.
     */
    public function test_6_2_selection_fluent_where_syntax(): void
    {
        // Using where() - more readable!
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        $result = Koi::query()->applyFilters($selection)->get();

        // Active kois with count > 5: Showa (10), Kohaku (20)
        $this->assertCount(2, $result);
    }

    /**
     * Comparison: add() vs where() syntax.
     */
    public function test_6_3_selection_add_vs_where_same_result(): void
    {
        // Using add()
        $selectionAdd = FilterSelection::make()
            ->add(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->add(FilterValue::for(KoiCountFilter::class)->gt(5));

        // Using where()
        $selectionWhere = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        // Both produce the same results
        $resultAdd = Koi::query()->applyFilters($selectionAdd)->get();
        $resultWhere = Koi::query()->applyFilters($selectionWhere)->get();

        $this->assertEquals(
            $resultAdd->pluck('id')->all(),
            $resultWhere->pluck('id')->all()
        );
    }

    /**
     * Selection inspection: Check what filters are in a selection.
     */
    public function test_6_4_selection_inspection(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->between(5, 20);

        // Check if selection has specific filters
        $this->assertTrue($selection->has(KoiStatusFilter::class));
        $this->assertTrue($selection->has(KoiCountFilter::class));
        $this->assertFalse($selection->has(PondWaterTypeFilter::class));

        // Get specific filter value
        $statusFilter = $selection->get(KoiStatusFilter::class);
        $this->assertEquals('active', $statusFilter->getValue());
        $this->assertEquals(new IsMatchMode, $statusFilter->getMatchMode());

        // Count filters
        $this->assertEquals(2, $selection->count());
        $this->assertTrue($selection->hasFilters());
    }

    /**
     * Selection modification: Add, remove, clear filters.
     */
    public function test_6_5_selection_modification(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        $this->assertEquals(2, $selection->count());

        // Remove a filter
        $selection->remove(KoiCountFilter::class);
        $this->assertEquals(1, $selection->count());
        $this->assertFalse($selection->has(KoiCountFilter::class));

        // Clear all filters
        $selection->clear();
        $this->assertEquals(0, $selection->count());
        $this->assertFalse($selection->hasFilters());
    }

    // ========================================================================
    // SECTION 7: SERIALIZATION (JSON)
    // ========================================================================
    // Save and load filter selections as JSON.
    // ========================================================================

    /**
     * Serialize selection to JSON for storage.
     */
    public function test_7_1_selection_to_json(): void
    {
        $selection = FilterSelection::make('My Saved Filter')
            ->description('A filter I want to save')
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(10);

        $json = $selection->toJson();
        $data = json_decode($json, true);

        // Check structure
        $this->assertEquals('My Saved Filter', $data['name']);
        $this->assertEquals('A filter I want to save', $data['description']);
        $this->assertCount(2, $data['filters']);

        // Check filter data
        $this->assertEquals('KoiStatusFilter', $data['filters'][0]['filter']);
        $this->assertEquals('is', $data['filters'][0]['mode']);
        $this->assertEquals('active', $data['filters'][0]['value']);
    }

    /**
     * Deserialize selection from JSON.
     */
    public function test_7_2_selection_from_json(): void
    {
        // This could come from a database or API
        $json = json_encode([
            'name' => 'Loaded Filter',
            'description' => 'Loaded from storage',
            'filters' => [
                ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
                ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 10],
            ],
        ]);

        $selection = FilterSelection::fromJson($json);

        $this->assertEquals('Loaded Filter', $selection->getName());
        $this->assertEquals(2, $selection->count());

        // Apply the loaded selection
        $result = Koi::query()->applyFilters($selection)->get();
        $this->assertCount(1, $result); // Only Kohaku (active, count=20)
    }

    /**
     * Round-trip: Save and reload produces identical results.
     */
    public function test_7_3_json_round_trip(): void
    {
        // Create original selection
        $original = FilterSelection::make('Round Trip Test')
            ->where(KoiStatusFilter::class)->any(['active', 'pending'])
            ->where(KoiCountFilter::class)->between(5, 15);

        // Save to JSON
        $json = $original->toJson();

        // Load from JSON
        $restored = FilterSelection::fromJson($json);

        // Both should produce identical results
        $originalResult = Koi::query()->applyFilters($original)->get();
        $restoredResult = Koi::query()->applyFilters($restored)->get();

        $this->assertEquals(
            $originalResult->pluck('id')->sort()->values()->all(),
            $restoredResult->pluck('id')->sort()->values()->all()
        );
    }

    // ========================================================================
    // SECTION 8: COMPLETE WORKFLOW EXAMPLES
    // ========================================================================
    // Real-world usage patterns combining all concepts.
    // ========================================================================

    /**
     * Workflow 1: Simple filter UI.
     *
     * Scenario: User selects a status from a dropdown.
     */
    public function test_8_1_workflow_simple_ui(): void
    {
        // Simulate user input from a form
        $userSelectedStatus = 'active';

        // Build and apply filter
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is($userSelectedStatus))
            ->get();

        $this->assertCount(2, $result);
    }

    /**
     * Workflow 2: Advanced filter form.
     *
     * Scenario: Form with multiple filter fields, some optional.
     */
    public function test_8_2_workflow_advanced_form(): void
    {
        // Simulate form input (some fields may be null)
        $formData = [
            'status' => ['active', 'pending'],  // Multi-select
            'min_count' => 5,
            'max_count' => 20,
            'water_type' => 'fresh',
        ];

        // Build selection based on form data
        $selection = FilterSelection::make();

        if (! empty($formData['status'])) {
            $selection->where(KoiStatusFilter::class)->any($formData['status']);
        }

        if (isset($formData['min_count']) && isset($formData['max_count'])) {
            $selection->where(KoiCountFilter::class)->between($formData['min_count'], $formData['max_count']);
        }

        if (! empty($formData['water_type'])) {
            $selection->where(PondWaterTypeFilter::class)->is($formData['water_type']);
        }

        $result = Koi::query()->applyFilters($selection)->get();

        // Active/pending kois, count 5-20, in fresh pond
        // Showa (active, 10, fresh) and Kohaku (active, 20, fresh)
        $this->assertCount(2, $result);
    }

    /**
     * Workflow 3: Saved filter presets.
     *
     * Scenario: Users can save and load filter presets.
     */
    public function test_8_3_workflow_saved_presets(): void
    {
        // Create some presets
        $presets = [
            'active_only' => FilterSelection::make('Active Only')
                ->where(KoiStatusFilter::class)->is('active')
                ->toJson(),

            'high_count' => FilterSelection::make('High Count')
                ->where(KoiCountFilter::class)->gt(10)
                ->toJson(),

            'fresh_water' => FilterSelection::make('Fresh Water')
                ->where(PondWaterTypeFilter::class)->is('fresh')
                ->toJson(),
        ];

        // Simulate storing in database and retrieving
        $selectedPreset = 'high_count';
        $loadedPresetJson = $presets[$selectedPreset];

        // Apply loaded preset
        $selection = FilterSelection::fromJson($loadedPresetJson);
        $result = Koi::query()->applyFilters($selection)->get();

        // Kois with count > 10: Kohaku (20), Asagi (15)
        $this->assertCount(2, $result);
    }

    /**
     * Workflow 4: API filter endpoint.
     *
     * Scenario: REST API that accepts filter parameters.
     */
    public function test_8_4_workflow_api_endpoint(): void
    {
        // Simulate API request body
        // Note: mode values are the enum string values (is, gt, lt, between, etc.)
        $requestBody = [
            'filters' => [
                ['filter' => 'KoiStatusFilter', 'mode' => 'any', 'value' => ['active', 'pending']],
                ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 5],
            ],
        ];

        // Build selection from request
        // Note: In real code, you'd validate the input first
        $selection = FilterSelection::fromArray([
            'filters' => $requestBody['filters'],
        ]);

        $result = Koi::query()->applyFilters($selection)->get();

        // Active/pending with count > 5: Showa (10), Kohaku (20), Asagi (15)
        $this->assertCount(3, $result);
    }

    /**
     * Workflow 5: Dynamic filters from configuration.
     *
     * Scenario: Filters are defined in config/database, not code.
     */
    public function test_8_5_workflow_dynamic_from_config(): void
    {
        // Simulate filter configuration from database/config
        $filterConfig = [
            [
                'key' => 'status_filter',
                'type' => 'select',
                'column' => 'status',
                'label' => 'Status',
                'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
            ],
            [
                'key' => 'count_filter',
                'type' => 'integer',
                'column' => 'count',
                'label' => 'Count',
            ],
        ];

        // Build dynamic filters from config
        $filters = [];
        foreach ($filterConfig as $config) {
            $filter = match ($config['type']) {
                'select' => SelectFilter::dynamic($config['key'])
                    ->withColumn($config['column'])
                    ->withLabel($config['label'])
                    ->withOptions($config['options'] ?? []),
                'integer' => IntegerFilter::dynamic($config['key'])
                    ->withColumn($config['column'])
                    ->withLabel($config['label']),
                default => throw new \InvalidArgumentException("Unknown type: {$config['type']}"),
            };
            $filters[] = $filter;
        }

        // Apply dynamic filters
        $result = QueryApplicator::for(Koi::query())
            ->withFilters($filters)
            ->applyFilters([
                new FilterValue('status_filter', new IsMatchMode, 'active'),
                new FilterValue('count_filter', new GreaterThanMatchMode, 5),
            ])
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
    }

    /**
     * Workflow 6: Combining static and dynamic filters.
     *
     * Scenario: Core filters as classes, custom filters as dynamic.
     */
    public function test_8_6_workflow_mixed_static_and_dynamic(): void
    {
        // Dynamic filter for a custom field
        $customFilter = IntegerFilter::dynamic('custom_count')
            ->withColumn('count')
            ->withLabel('Custom Count Filter');

        // Combine with model's static filters
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([
                KoiStatusFilter::class,  // Static (from class)
                $customFilter,           // Dynamic (runtime)
            ])
            ->applyFilters([
                new FilterValue('KoiStatusFilter', new IsMatchMode, 'active'),
                new FilterValue('custom_count', new BetweenMatchMode, ['min' => 5, 'max' => 15]),
            ])
            ->getQuery()
            ->get();

        // Active with count 5-15: Showa (10)
        $this->assertCount(1, $result);
        $this->assertEquals('Showa', $result->first()->name);
    }

    // ========================================================================
    // SECTION 9: VALUE SANITIZATION & VALIDATION
    // ========================================================================
    // Filters automatically sanitize and validate input values.
    // ========================================================================

    /**
     * Sanitization: Input values are automatically cleaned/converted.
     *
     * Each filter type has a sanitizeValue() method that transforms input:
     * - BooleanFilter: "true", "1", "yes" → true
     * - IntegerFilter: "123" → 123, array → BetweenValue
     * - TextFilter: trims whitespace
     */
    public function test_9_1_automatic_sanitization(): void
    {
        // String "true" is sanitized to boolean true
        $boolFilter = KoiActiveFilter::make();
        $this->assertTrue($boolFilter->sanitizeValue('true', new IsMatchMode));
        $this->assertTrue($boolFilter->sanitizeValue('1', new IsMatchMode));
        $this->assertTrue($boolFilter->sanitizeValue('yes', new IsMatchMode));
        $this->assertFalse($boolFilter->sanitizeValue('false', new IsMatchMode));
        $this->assertFalse($boolFilter->sanitizeValue('0', new IsMatchMode));

        // String numbers are sanitized to integers
        $intFilter = KoiCountFilter::make();
        $this->assertSame(123, $intFilter->sanitizeValue('123', new IsMatchMode));

        // Arrays are converted to BetweenValue for BETWEEN mode
        $betweenValue = $intFilter->sanitizeValue(['min' => 5, 'max' => 15], new BetweenMatchMode);
        $this->assertInstanceOf(BetweenValue::class, $betweenValue);
        $this->assertEquals(5, $betweenValue->min);
        $this->assertEquals(15, $betweenValue->max);
    }

    /**
     * Validation: Invalid values throw FilterValidationException.
     *
     * Each filter type defines validationRules() using Laravel validation.
     * When validation fails, a FilterValidationException is thrown.
     */
    public function test_9_2_validation_throws_exception_for_invalid_values(): void
    {
        // Boolean filter rejects non-boolean values after sanitization
        $this->expectException(FilterValidationException::class);

        $boolFilter = KoiActiveFilter::make();

        QueryApplicator::for(Koi::query())
            ->withFilters([$boolFilter])
            ->applyFilter(new FilterValue('KoiActiveFilter', new IsMatchMode, 'banana'));
    }

    /**
     * SelectFilter validates values against defined options.
     */
    public function test_9_3_select_filter_validates_options(): void
    {
        $this->expectException(FilterValidationException::class);

        // KoiStatusFilter only allows: active, inactive, pending
        QueryApplicator::for(Koi::query())
            ->withFilters([KoiStatusFilter::class])
            ->applyFilter(new FilterValue('KoiStatusFilter', new IsMatchMode, 'invalid_status'));
    }

    /**
     * FilterValidationException provides detailed error information.
     */
    public function test_9_4_validation_exception_details(): void
    {
        try {
            $boolFilter = KoiActiveFilter::make();

            QueryApplicator::for(Koi::query())
                ->withFilters([$boolFilter])
                ->applyFilter(new FilterValue('KoiActiveFilter', new IsMatchMode, 'invalid'));

            $this->fail('Expected FilterValidationException');
        } catch (FilterValidationException $e) {
            // Exception contains filter key
            $this->assertEquals('KoiActiveFilter', $e->getFilterKey());

            // Exception contains validation errors
            $errors = $e->getErrors();
            $this->assertArrayHasKey('value', $errors);

            // Can get first error messages
            $firstErrors = $e->getFirstErrors();
            $this->assertNotEmpty($firstErrors);
        }
    }

    // ========================================================================
    // SECTION 10: TYPE-SAFE VALUES (typedValue)
    // ========================================================================
    // Filters provide type-safe value methods for strict typing.
    // ========================================================================

    /**
     * typedValue(): Directly use strict-typed values, bypassing sanitization.
     *
     * Each filter defines a typedValue() method with strict type hints:
     * - BooleanFilter::typedValue(bool $value)
     * - IntegerFilter::typedValue(int|BetweenValue $value)
     * - TextFilter::typedValue(string $value)
     * - SelectFilter::typedValue(string|array $value)
     */
    public function test_10_1_typed_value_method(): void
    {
        // Boolean filter expects bool
        $boolFilter = KoiActiveFilter::make();
        $this->assertTrue($boolFilter->typedValue(true));
        $this->assertFalse($boolFilter->typedValue(false));

        // Integer filter expects int or BetweenValue
        $intFilter = KoiCountFilter::make();
        $this->assertEquals(42, $intFilter->typedValue(42));

        // BetweenValue for range queries
        $between = new BetweenValue(5, 15);
        $result = $intFilter->typedValue($between);
        $this->assertInstanceOf(BetweenValue::class, $result);
    }

    /**
     * BetweenValue: Type-safe DTO for range values.
     */
    public function test_10_2_between_value_dto(): void
    {
        // Create directly
        $between = new BetweenValue(10, 100);
        $this->assertEquals(10, $between->min);
        $this->assertEquals(100, $between->max);

        // Create from array
        $fromArray = BetweenValue::fromArray(['min' => 5, 'max' => 50]);
        $this->assertEquals(5, $fromArray->min);
        $this->assertEquals(50, $fromArray->max);

        // Also works with indexed arrays
        $fromIndexed = BetweenValue::fromArray([1, 10]);
        $this->assertEquals(1, $fromIndexed->min);
        $this->assertEquals(10, $fromIndexed->max);

        // Convert to array
        $array = $between->toArray();
        $this->assertEquals(['min' => 10, 'max' => 100], $array);
    }

    /**
     * Using BetweenValue in queries.
     */
    public function test_10_3_between_value_in_query(): void
    {
        // BetweenValue is automatically converted for query application
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiCountFilter::class)->between(5, 15))
            ->get();

        // Kois with count 5-15: Showa (10), Sanke (5), Asagi (15)
        $this->assertCount(3, $result);
    }

    // ========================================================================
    // SECTION 11: CUSTOM MATCH MODES (Extensibility)
    // ========================================================================
    // The MatchMode system is fully extensible via MatchModeContract.
    // ========================================================================

    /**
     * MatchModes are now classes instead of enums.
     *
     * Each MatchMode implements MatchModeContract with:
     * - key(): string - unique identifier (e.g., 'is', 'contains', 'gt')
     * - apply(): void - the query logic
     *
     * Built-in modes:
     * - IsMatchMode, IsNotMatchMode
     * - ContainsMatchMode, StartsWithMatchMode, EndsWithMatchMode
     * - AnyMatchMode, AllMatchMode, NoneMatchMode
     * - GreaterThanMatchMode, GreaterThanOrEqualMatchMode
     * - LessThanMatchMode, LessThanOrEqualMatchMode
     * - BetweenMatchMode
     * - EmptyMatchMode, NotEmptyMatchMode
     */
    public function test_11_1_matchmode_classes(): void
    {
        // MatchModes are instantiated directly
        $isMode = new IsMatchMode;
        $this->assertEquals('is', $isMode->key());

        $gtMode = new GreaterThanMatchMode;
        $this->assertEquals('gt', $gtMode->key());

        // Use in FilterValue
        $filterValue = new FilterValue('KoiStatusFilter', new IsMatchMode, 'active');
        $this->assertInstanceOf(IsMatchMode::class, $filterValue->getMatchMode());

        // Or use fluent builder (recommended)
        $filterValue2 = FilterValue::for(KoiStatusFilter::class)->is('active');
        $this->assertInstanceOf(IsMatchMode::class, $filterValue2->getMatchMode());
    }

    /**
     * Two-level query logic: MatchMode AND Filter can define logic.
     *
     * Priority:
     * 1. Filter::apply() - if returns true, custom logic was applied
     * 2. MatchMode::apply() - default fallback
     *
     * This allows:
     * - Standard modes work out-of-the-box via MatchMode classes
     * - Filters can override for special cases
     */
    public function test_11_2_two_level_query_logic(): void
    {
        // Standard MatchMode logic is used by default
        // IsMatchMode::apply() generates: WHERE column = value
        $result = Koi::query()
            ->applyFilter(FilterValue::for(KoiStatusFilter::class)->is('active'))
            ->get();

        $this->assertCount(2, $result);

        // Filters CAN override with custom logic via Filter::apply()
        // See KoiStatusFilter - if it had custom apply(), it would be used first
    }

    /**
     * MatchMode factory with magic method access.
     *
     * The MatchMode class provides static factory methods:
     * - MatchMode::is() → IsMatchMode
     * - MatchMode::contains() → ContainsMatchMode
     * - MatchMode::gt() → GreaterThanMatchMode
     * etc.
     */
    public function test_11_3_matchmode_factory(): void
    {
        // Factory methods return mode instances via __callStatic
        $this->assertInstanceOf(IsMatchMode::class, MatchMode::is());
        $this->assertInstanceOf(GreaterThanMatchMode::class, MatchMode::gt());
        $this->assertInstanceOf(BetweenMatchMode::class, MatchMode::between());

        // All built-in modes are accessible:
        // MatchMode::is(), isNot()
        // MatchMode::contains(), startsWith(), endsWith(), regex()
        // MatchMode::any(), all(), none()
        // MatchMode::gt(), gte(), lt(), lte()
        // MatchMode::between()
        // MatchMode::empty(), notEmpty()
    }

    /**
     * Custom MatchModes can be registered.
     *
     * Create your own MatchMode by:
     * 1. Implementing MatchModeContract
     * 2. Registering via MatchMode::register()
     * 3. Accessing via magic method
     *
     * Example (not executed):
     * ```php
     * class StartsWithMatchMode implements MatchModeContract {
     *     public function key(): string { return 'starts_with'; }
     *     public function apply(Builder $q, string $col, mixed $val): void {
     *         $q->where($col, 'LIKE', $val . '%');
     *     }
     * }
     *
     * MatchMode::register('startsWith', StartsWithMatchMode::class);
     * MatchMode::startsWith(); // Returns StartsWithMatchMode instance
     * ```
     */
    public function test_11_4_custom_matchmode_extensibility(): void
    {
        // Built-in modes are pre-registered and accessible via get()
        $this->assertInstanceOf(IsMatchMode::class, MatchMode::get('is'));
        $this->assertInstanceOf(GreaterThanMatchMode::class, MatchMode::get('gt'));
        $this->assertInstanceOf(BetweenMatchMode::class, MatchMode::get('between'));

        // Verify all built-in modes exist
        $builtInModes = ['is', 'is_not', 'contains', 'any', 'none', 'gt', 'lt', 'between', 'empty', 'not_empty'];
        foreach ($builtInModes as $key) {
            $mode = MatchMode::get($key);
            $this->assertEquals($key, $mode->key());
        }
    }

    // ========================================================================
    // SECTION 12: OR LOGIC & FILTER GROUPS
    // ========================================================================
    // Complex nested AND/OR conditions with FilterGroup.
    // ========================================================================

    /**
     * FilterGroup: A container for filter conditions with AND/OR logic.
     *
     * By default, FilterSelection uses AND logic:
     * status = 'active' AND count > 5
     *
     * But sometimes you need OR logic:
     * status = 'active' OR status = 'pending'
     *
     * FilterGroup solves this with nested groups that can use AND or OR.
     */
    public function test_12_1_filter_group_basics(): void
    {
        // AND group (default)
        $andGroup = FilterGroup::and();
        $this->assertEquals(GroupOperatorEnum::AND, $andGroup->getOperator());

        // OR group
        $orGroup = FilterGroup::or();
        $this->assertEquals(GroupOperatorEnum::OR, $orGroup->getOperator());

        // Add filters to a group
        $andGroup->where(KoiStatusFilter::class)->is('active');
        $andGroup->where(KoiCountFilter::class)->gt(5);

        $this->assertEquals(2, $andGroup->count());
        $this->assertFalse($andGroup->isEmpty());
    }

    /**
     * Simple OR logic: status = 'active' OR status = 'pending'.
     *
     * Use FilterSelection::makeOr() to create a selection with OR logic
     * instead of the default AND logic.
     */
    public function test_12_2_simple_or_logic(): void
    {
        // Create an OR selection (top-level OR)
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiStatusFilter::class)->is('pending');

        // This generates: status = 'active' OR status = 'pending'
        $result = Koi::query()->applySelection($selection)->get();

        // Active: Showa, Kohaku
        // Pending: Asagi, Shusui
        $this->assertCount(4, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa', 'Shusui'], $names);
    }

    /**
     * Nested OR group within AND selection.
     *
     * Use orWhere() to add an OR group to an AND selection:
     * count > 5 AND (status = 'active' OR status = 'pending')
     */
    public function test_12_3_and_with_nested_or(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiCountFilter::class)->gt(5)
            ->orWhere(function (FilterGroup $group) {
                $group->where(KoiStatusFilter::class)->is('active');
                $group->where(KoiStatusFilter::class)->is('pending');
            });

        // This generates: count > 5 AND (status = 'active' OR status = 'pending')
        $result = Koi::query()->applySelection($selection)->get();

        // count > 5: Showa (10), Kohaku (20), Asagi (15)
        // AND (active OR pending): all three match
        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa'], $names);
    }

    /**
     * Complex nested groups: (A AND B) OR (C AND D).
     *
     * Use andWhere() within makeOr() to create complex conditions:
     * (status = 'active' AND count > 15) OR (status = 'pending')
     */
    public function test_12_4_complex_nested_groups(): void
    {
        // (status = 'active' AND count > 15) OR (status = 'pending')
        $selection = FilterSelection::makeOr()
            ->andWhere(function (FilterGroup $group) {
                $group->where(KoiStatusFilter::class)->is('active');
                $group->where(KoiCountFilter::class)->gt(15);
            })
            ->andWhere(function (FilterGroup $group) {
                $group->where(KoiStatusFilter::class)->is('pending');
            });

        $result = Koi::query()->applySelection($selection)->get();

        // (active AND count > 15): Kohaku (20)
        // OR pending: Asagi, Shusui
        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Shusui'], $names);
    }

    /**
     * Deeply nested groups: A AND ((B AND C) OR (D AND E)).
     *
     * Groups can be nested multiple levels deep for complex logic.
     */
    public function test_12_5_deeply_nested_groups(): void
    {
        // count >= 5 AND ((status = 'active' AND count > 10) OR (status = 'inactive'))
        $selection = FilterSelection::make()
            ->where(KoiCountFilter::class)->gt(4) // >= 5
            ->orWhere(function (FilterGroup $or) {
                $or->andWhere(function (FilterGroup $and) {
                    $and->where(KoiStatusFilter::class)->is('active');
                    $and->where(KoiCountFilter::class)->gt(10);
                });
                $or->andWhere(function (FilterGroup $and) {
                    $and->where(KoiStatusFilter::class)->is('inactive');
                });
            });

        $result = Koi::query()->applySelection($selection)->get();

        // count >= 5: Showa (10), Kohaku (20), Sanke (5), Asagi (15)
        // AND ((active AND count > 10) OR inactive):
        //   - active AND count > 10: Kohaku (20)
        //   - inactive: Sanke
        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Kohaku', 'Sanke'], $names);
    }

    /**
     * OR logic with relation filters.
     *
     * Works seamlessly with relation filters via whereHas.
     */
    public function test_12_6_or_with_relation_filters(): void
    {
        // water_type = 'fresh' OR water_type = 'brackish'
        $selection = FilterSelection::makeOr()
            ->where(PondWaterTypeFilter::class)->is('fresh')
            ->where(PondWaterTypeFilter::class)->is('brackish');

        $result = Koi::query()->applySelection($selection)->get();

        // Fresh pond: Showa, Kohaku
        // Brackish pond: Asagi
        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asagi', 'Kohaku', 'Showa'], $names);
    }

    /**
     * Mixed direct and relation filters with OR.
     */
    public function test_12_7_mixed_filters_with_or(): void
    {
        // status = 'pending' OR water_type = 'fresh'
        $selection = FilterSelection::makeOr()
            ->where(KoiStatusFilter::class)->is('pending')
            ->where(PondWaterTypeFilter::class)->is('fresh');

        $result = Koi::query()->applySelection($selection)->get();

        // pending: Asagi, Shusui
        // fresh pond: Showa, Kohaku
        $this->assertCount(4, $result);
    }

    /**
     * Serialization with nested groups.
     *
     * Complex selections are serialized with a 'group' key instead of 'filters'.
     * Simple AND-only selections use the legacy 'filters' format for compatibility.
     */
    public function test_12_8_serialization_with_groups(): void
    {
        // Simple AND selection uses legacy format
        $simpleSelection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->where(KoiCountFilter::class)->gt(5);

        $simpleArray = $simpleSelection->toArray();
        $this->assertArrayHasKey('filters', $simpleArray);
        $this->assertArrayNotHasKey('group', $simpleArray);

        // Complex selection with OR uses new group format
        $complexSelection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('pending');
            });

        $complexArray = $complexSelection->toArray();
        $this->assertArrayHasKey('group', $complexArray);
        $this->assertArrayNotHasKey('filters', $complexArray);
        $this->assertEquals('and', $complexArray['group']['operator']);
    }

    /**
     * JSON round-trip with nested groups.
     *
     * Complex selections can be saved and restored from JSON.
     */
    public function test_12_9_json_round_trip_with_groups(): void
    {
        // Create complex selection
        $original = FilterSelection::make('Complex Query')
            ->where(KoiCountFilter::class)->gt(5)
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('active');
                $g->where(KoiStatusFilter::class)->is('pending');
            });

        // Serialize to JSON
        $json = $original->toJson();

        // Restore from JSON
        $restored = FilterSelection::fromJson($json);

        // Compare results
        $originalResult = Koi::query()->applySelection($original)->get();
        $restoredResult = Koi::query()->applySelection($restored)->get();

        $this->assertEquals(
            $originalResult->pluck('id')->sort()->values()->all(),
            $restoredResult->pluck('id')->sort()->values()->all()
        );
    }

    /**
     * Real-world workflow: User can select "any of these statuses".
     *
     * Common UI pattern where user can select multiple status values
     * with OR logic between them.
     */
    public function test_12_10_workflow_multi_status_selection(): void
    {
        // Simulate user selecting multiple statuses in a UI
        $selectedStatuses = ['active', 'pending'];
        $minCount = 10;

        // Build query: count >= minCount AND status IN (active, pending)
        // Note: This can be done with ANY mode for simple cases
        $simpleWay = Koi::query()
            ->applyFilters([
                FilterValue::for(KoiStatusFilter::class)->any($selectedStatuses),
                FilterValue::for(KoiCountFilter::class)->gt($minCount - 1),
            ])
            ->get();

        // Or with explicit OR groups (more flexible for complex cases)
        $selection = FilterSelection::make()
            ->where(KoiCountFilter::class)->gt($minCount - 1)
            ->orWhere(function (FilterGroup $g) use ($selectedStatuses) {
                foreach ($selectedStatuses as $status) {
                    $g->where(KoiStatusFilter::class)->is($status);
                }
            });

        $orWay = Koi::query()->applySelection($selection)->get();

        // Both should return: Showa (10, active), Kohaku (20, active), Asagi (15, pending)
        $this->assertCount(3, $simpleWay);
        $this->assertCount(3, $orWay);
    }

    /**
     * FilterGroup inspection methods.
     */
    public function test_12_11_filter_group_inspection(): void
    {
        $selection = FilterSelection::make()
            ->where(KoiStatusFilter::class)->is('active')
            ->orWhere(function (FilterGroup $g) {
                $g->where(KoiStatusFilter::class)->is('pending');
                $g->where(KoiCountFilter::class)->gt(10);
            });

        // Check if selection has nested groups
        $this->assertTrue($selection->hasNestedGroups());

        // Get the root group
        $rootGroup = $selection->getGroup();
        $this->assertEquals(GroupOperatorEnum::AND, $rootGroup->getOperator());

        // Get all filter values (flattened)
        $allFilters = $selection->all();
        $this->assertCount(3, $allFilters);

        // Get all unique filter keys
        $keys = $rootGroup->getAllFilterKeys();
        $this->assertCount(2, $keys); // KoiStatusFilter, KoiCountFilter
    }
}
