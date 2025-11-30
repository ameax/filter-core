<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Data\BetweenValue;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Filters\DecimalFilter;
use Ameax\FilterCore\Filters\Dynamic\DynamicDecimalFilter;
use Ameax\FilterCore\MatchModes\AnyMatchMode;
use Ameax\FilterCore\MatchModes\BetweenMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanOrEqualMatchMode;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\IsNotMatchMode;
use Ameax\FilterCore\MatchModes\LessThanMatchMode;
use Ameax\FilterCore\MatchModes\LessThanOrEqualMatchMode;
use Ameax\FilterCore\MatchModes\NoneMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Ameax\FilterCore\Tests\TestCase;

class DecimalFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create pond
        $pond = Pond::create(['name' => 'Test Pond', 'water_type' => 'fresh', 'capacity' => 5000, 'is_heated' => true]);

        // Create kois with varying weights and prices
        Koi::create([
            'name' => 'Small Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 1.50,
            'price_cents' => 1999, // $19.99
        ]);
        Koi::create([
            'name' => 'Medium Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 3.75,
            'price_cents' => 4999, // $49.99
        ]);
        Koi::create([
            'name' => 'Large Koi',
            'status' => 'active',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 7.25,
            'price_cents' => 9999, // $99.99
        ]);
        Koi::create([
            'name' => 'Giant Koi',
            'status' => 'pending',
            'count' => 1,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 12.00,
            'price_cents' => 19999, // $199.99
        ]);
        Koi::create([
            'name' => 'Baby Koi',
            'status' => 'active',
            'count' => 5,
            'is_active' => true,
            'pond_id' => $pond->id,
            'weight' => 0.25,
            'price_cents' => 999, // $9.99
        ]);
    }

    // ========================================
    // Filter Class Tests
    // ========================================

    public function test_decimal_filter_returns_correct_type(): void
    {
        $filter = KoiWeightFilter::make();

        $this->assertEquals(FilterTypeEnum::DECIMAL, $filter->type());
        $this->assertEquals('weight', $filter->column());
        $this->assertEquals('Weight (kg)', $filter->label());
    }

    public function test_decimal_filter_returns_correct_precision(): void
    {
        $filter = KoiWeightFilter::make();

        $this->assertEquals(2, $filter->precision());
    }

    public function test_decimal_filter_returns_min_max(): void
    {
        $filter = KoiWeightFilter::make();

        $this->assertEquals(0.0, $filter->min());
        $this->assertEquals(100.0, $filter->max());
    }

    public function test_decimal_filter_returns_allowed_modes(): void
    {
        $filter = KoiWeightFilter::make();
        $modes = $filter->allowedModes();

        $this->assertCount(9, $modes);
        $this->assertInstanceOf(IsMatchMode::class, $modes[0]);
        $this->assertInstanceOf(IsNotMatchMode::class, $modes[1]);
        $this->assertInstanceOf(AnyMatchMode::class, $modes[2]);
        $this->assertInstanceOf(NoneMatchMode::class, $modes[3]);
        $this->assertInstanceOf(GreaterThanMatchMode::class, $modes[4]);
        $this->assertInstanceOf(GreaterThanOrEqualMatchMode::class, $modes[5]);
        $this->assertInstanceOf(LessThanMatchMode::class, $modes[6]);
        $this->assertInstanceOf(LessThanOrEqualMatchMode::class, $modes[7]);
        $this->assertInstanceOf(BetweenMatchMode::class, $modes[8]);
    }

    public function test_stored_as_integer_returns_correct_value(): void
    {
        $weightFilter = KoiWeightFilter::make();
        $priceFilter = KoiPriceFilter::make();

        $this->assertFalse($weightFilter->storedAsInteger());
        $this->assertTrue($priceFilter->storedAsInteger());
    }

    // ========================================
    // Value Sanitization Tests
    // ========================================

    public function test_sanitize_string_to_float(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new IsMatchMode;

        $this->assertEquals(19.99, $filter->sanitizeValue('19.99', $mode));
        $this->assertEquals(5.0, $filter->sanitizeValue('5', $mode));
    }

    public function test_sanitize_int_to_float(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new IsMatchMode;

        $this->assertEquals(5.0, $filter->sanitizeValue(5, $mode));
    }

    public function test_sanitize_rounds_to_precision(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new IsMatchMode;

        // Should round to 2 decimal places
        $this->assertEquals(19.99, $filter->sanitizeValue(19.994, $mode));
        $this->assertEquals(20.0, $filter->sanitizeValue(19.995, $mode));
    }

    public function test_sanitize_null_returns_null(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new IsMatchMode;

        $this->assertNull($filter->sanitizeValue(null, $mode));
    }

    public function test_sanitize_array_for_any_mode(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new AnyMatchMode;

        $result = $filter->sanitizeValue(['1.5', 2, 3.756], $mode);

        $this->assertEquals([1.5, 2.0, 3.76], $result);
    }

    public function test_sanitize_between_array(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new BetweenMatchMode;

        $result = $filter->sanitizeValue(['min' => '1.5', 'max' => '10.5'], $mode);

        $this->assertEquals(['min' => 1.5, 'max' => 10.5], $result);
    }

    public function test_sanitize_between_value_object(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new BetweenMatchMode;

        $result = $filter->sanitizeValue(new BetweenValue(1.5, 10.5), $mode);

        $this->assertInstanceOf(BetweenValue::class, $result);
        $this->assertEquals(1.5, $result->min);
        $this->assertEquals(10.5, $result->max);
    }

    // ========================================
    // Query Filter Tests - Regular Decimal
    // ========================================

    public function test_filter_weight_is(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->is(3.75))
            ->getQuery()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Medium Koi', $result->first()->name);
    }

    public function test_filter_weight_is_not(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->isNot(3.75))
            ->getQuery()
            ->get();

        $this->assertCount(4, $result);
        $this->assertFalse($result->pluck('name')->contains('Medium Koi'));
    }

    public function test_filter_weight_greater_than(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->gt(5.0))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Giant Koi', 'Large Koi'], $names);
    }

    public function test_filter_weight_greater_than_or_equal(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->gte(7.25))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Giant Koi', 'Large Koi'], $names);
    }

    public function test_filter_weight_less_than(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->lt(2.0))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Baby Koi', 'Small Koi'], $names);
    }

    public function test_filter_weight_less_than_or_equal(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->lte(1.5))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Baby Koi', 'Small Koi'], $names);
    }

    public function test_filter_weight_between(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->between(1.0, 8.0))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Large Koi', 'Medium Koi', 'Small Koi'], $names);
    }

    public function test_filter_weight_any(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->any([1.5, 7.25, 12.0]))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Giant Koi', 'Large Koi', 'Small Koi'], $names);
    }

    public function test_filter_weight_none(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiWeightFilter::class])
            ->applyFilter(FilterValue::for(KoiWeightFilter::class)->none([1.5, 7.25]))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Baby Koi', 'Giant Koi', 'Medium Koi'], $names);
    }

    // ========================================
    // Query Filter Tests - Stored As Integer
    // ========================================

    public function test_filter_price_is_converts_to_cents(): void
    {
        // User enters 19.99, should match DB value 1999
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiPriceFilter::class])
            ->applyFilter(FilterValue::for(KoiPriceFilter::class)->is(19.99))
            ->getQuery()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Small Koi', $result->first()->name);
        $this->assertEquals(1999, $result->first()->price_cents);
    }

    public function test_filter_price_greater_than_converts_to_cents(): void
    {
        // User enters 50.00, should convert to 5000
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiPriceFilter::class])
            ->applyFilter(FilterValue::for(KoiPriceFilter::class)->gt(50.00))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Giant Koi', 'Large Koi'], $names);
    }

    public function test_filter_price_between_converts_to_cents(): void
    {
        // User enters 20.00-100.00, should convert to 2000-10000
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiPriceFilter::class])
            ->applyFilter(FilterValue::for(KoiPriceFilter::class)->between(20.00, 100.00))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Large Koi', 'Medium Koi'], $names);
    }

    public function test_filter_price_any_converts_to_cents(): void
    {
        // User enters [19.99, 49.99], should convert to [1999, 4999]
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiPriceFilter::class])
            ->applyFilter(FilterValue::for(KoiPriceFilter::class)->any([19.99, 49.99]))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Medium Koi', 'Small Koi'], $names);
    }

    public function test_filter_price_less_than_or_equal_converts_to_cents(): void
    {
        // User enters 19.99, should convert to 1999
        $result = QueryApplicator::for(Koi::query())
            ->withFilters([KoiPriceFilter::class])
            ->applyFilter(FilterValue::for(KoiPriceFilter::class)->lte(19.99))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Baby Koi', 'Small Koi'], $names);
    }

    // ========================================
    // Dynamic Decimal Filter Tests
    // ========================================

    public function test_dynamic_decimal_filter_basic(): void
    {
        $filter = DecimalFilter::dynamic('weight')
            ->withColumn('weight')
            ->withLabel('Weight')
            ->withPrecision(2);

        $this->assertEquals('weight', $filter->getKey());
        $this->assertEquals('weight', $filter->column());
        $this->assertEquals('Weight', $filter->label());
        $this->assertEquals(2, $filter->precision());
        $this->assertEquals(FilterTypeEnum::DECIMAL, $filter->type());
    }

    public function test_dynamic_decimal_filter_with_stored_as_integer(): void
    {
        $filter = DynamicDecimalFilter::create('price')
            ->withColumn('price_cents')
            ->withLabel('Price')
            ->withPrecision(2)
            ->withStoredAsInteger(true)
            ->withMin(0.0)
            ->withMax(99999.99);

        $this->assertTrue($filter->storedAsInteger());
        $this->assertEquals(0.0, $filter->min());
        $this->assertEquals(99999.99, $filter->max());
    }

    public function test_dynamic_decimal_filter_in_query(): void
    {
        $priceFilter = DynamicDecimalFilter::create('price')
            ->withColumn('price_cents')
            ->withPrecision(2)
            ->withStoredAsInteger(true);

        $result = QueryApplicator::for(Koi::query())
            ->withFilters([$priceFilter])
            ->applyFilter(new FilterValue('price', new IsMatchMode, 49.99))
            ->getQuery()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Medium Koi', $result->first()->name);
    }

    public function test_dynamic_decimal_filter_with_relation(): void
    {
        $filter = DynamicDecimalFilter::create('weight')
            ->withColumn('weight')
            ->withRelation('kois');

        $this->assertEquals('kois', $filter->getRelation());
    }

    public function test_dynamic_decimal_filter_with_meta(): void
    {
        $filter = DynamicDecimalFilter::create('price')
            ->withMeta(['currency' => 'USD', 'symbol' => '$']);

        $this->assertEquals(['currency' => 'USD', 'symbol' => '$'], $filter->meta());
    }

    public function test_dynamic_decimal_filter_nullable(): void
    {
        $filter = DynamicDecimalFilter::create('price')
            ->withNullable(true);

        $this->assertTrue($filter->nullable());
    }

    // ========================================
    // Float Precision Edge Cases
    // ========================================

    public function test_float_precision_rounding(): void
    {
        $filter = KoiWeightFilter::make();
        $mode = new IsMatchMode;

        // Classic float precision issue: 0.1 + 0.2 = 0.30000000000000004
        $result = $filter->sanitizeValue(0.1 + 0.2, $mode);
        $this->assertEquals(0.30, $result);
    }

    public function test_stored_as_integer_conversion_precision(): void
    {
        $filter = KoiPriceFilter::make();

        // Test the protected toStorageValue method via reflection
        $reflection = new \ReflectionClass($filter);
        $method = $reflection->getMethod('toStorageValue');
        $method->setAccessible(true);

        // 19.99 should become 1999
        $this->assertEquals(1999, $method->invoke($filter, 19.99));

        // 0.01 should become 1
        $this->assertEquals(1, $method->invoke($filter, 0.01));

        // 100.00 should become 10000
        $this->assertEquals(10000, $method->invoke($filter, 100.00));

        // Float precision issue: 19.994 should round to 19.99 = 1999
        $this->assertEquals(1999, $method->invoke($filter, 19.994));
    }

    // ========================================
    // Filter Definition Tests
    // ========================================

    public function test_filter_converts_to_definition(): void
    {
        $filter = KoiWeightFilter::make();
        $definition = $filter->toDefinition();

        $this->assertEquals('KoiWeightFilter', $definition->getKey());
        $this->assertEquals(FilterTypeEnum::DECIMAL, $definition->getType());
        $this->assertEquals('weight', $definition->getColumn());
        $this->assertEquals('Weight (kg)', $definition->getLabel());
        $this->assertCount(9, $definition->getAllowedMatchModes());
    }

    public function test_dynamic_filter_converts_to_definition(): void
    {
        $filter = DynamicDecimalFilter::create('custom_price')
            ->withColumn('price_cents')
            ->withLabel('Custom Price')
            ->withPrecision(2)
            ->withStoredAsInteger(true);

        $definition = $filter->toDefinition();

        $this->assertEquals('custom_price', $definition->getKey());
        $this->assertEquals(FilterTypeEnum::DECIMAL, $definition->getType());
        $this->assertEquals('price_cents', $definition->getColumn());
        $this->assertEquals('Custom Price', $definition->getLabel());
    }
}
