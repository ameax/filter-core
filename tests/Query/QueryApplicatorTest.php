<?php

namespace Ameax\FilterCore\Tests\Query;

use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Enums\MatchModeEnum;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\TestCase;
use InvalidArgumentException;

class QueryApplicatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        Koi::create(['name' => 'Showa', 'status' => 'active', 'count' => 10, 'is_active' => true, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Kohaku', 'status' => 'active', 'count' => 20, 'is_active' => true, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Sanke', 'status' => 'inactive', 'count' => 5, 'is_active' => false, 'variety' => 'Gosanke']);
        Koi::create(['name' => 'Asagi', 'status' => 'pending', 'count' => 15, 'is_active' => true, 'variety' => null]);
        Koi::create(['name' => 'Shusui', 'status' => 'pending', 'count' => 0, 'is_active' => false, 'variety' => null]);
    }

    protected function getDefinitions(): array
    {
        return [
            new FilterDefinition(
                key: 'status',
                type: FilterTypeEnum::SELECT,
                column: 'status',
            ),
            new FilterDefinition(
                key: 'count',
                type: FilterTypeEnum::INTEGER,
                column: 'count',
            ),
            new FilterDefinition(
                key: 'name',
                type: FilterTypeEnum::TEXT,
                column: 'name',
            ),
            new FilterDefinition(
                key: 'is_active',
                type: FilterTypeEnum::BOOLEAN,
                column: 'is_active',
            ),
            new FilterDefinition(
                key: 'variety',
                type: FilterTypeEnum::SELECT,
                column: 'variety',
                nullable: true,
            ),
        ];
    }

    public function test_applies_is_match_mode_with_single_value(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('status', MatchModeEnum::IS, 'active'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Kohaku', 'Showa'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_applies_is_match_mode_with_array_value(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('status', MatchModeEnum::IS, ['active', 'pending']))
            ->getQuery()
            ->get();

        $this->assertCount(4, $result);
    }

    public function test_applies_is_not_match_mode_with_single_value(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('status', MatchModeEnum::IS_NOT, 'active'))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $this->assertFalse($result->pluck('status')->contains('active'));
    }

    public function test_applies_is_not_match_mode_with_array_value(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('status', MatchModeEnum::IS_NOT, ['active', 'pending']))
            ->getQuery()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('inactive', $result->first()->status);
    }

    public function test_applies_any_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('status', MatchModeEnum::ANY, ['active', 'inactive']))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
    }

    public function test_applies_none_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('status', MatchModeEnum::NONE, ['active', 'inactive']))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'pending'));
    }

    public function test_applies_greater_than_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('count', MatchModeEnum::GREATER_THAN, 10))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count > 10));
    }

    public function test_applies_less_than_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('count', MatchModeEnum::LESS_THAN, 10))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count < 10));
    }

    public function test_applies_between_match_mode_with_indexed_array(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('count', MatchModeEnum::BETWEEN, [5, 15]))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->count >= 5 && $koi->count <= 15));
    }

    public function test_applies_between_match_mode_with_named_array(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('count', MatchModeEnum::BETWEEN, ['min' => 5, 'max' => 15]))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
    }

    public function test_between_throws_exception_for_non_array_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BETWEEN match mode requires an array value');

        QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('count', MatchModeEnum::BETWEEN, 10));
    }

    public function test_between_throws_exception_for_incomplete_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BETWEEN match mode requires both min and max values');

        QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('count', MatchModeEnum::BETWEEN, [10]));
    }

    public function test_applies_contains_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('name', MatchModeEnum::CONTAINS, 'Sh'))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Showa', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_applies_boolean_filter(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('is_active', MatchModeEnum::IS, true))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->is_active === true));
    }

    public function test_applies_multiple_filters(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilters([
                FilterValue::make('status', MatchModeEnum::IS, 'active'),
                FilterValue::make('count', MatchModeEnum::GREATER_THAN, 5),
            ])
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->status === 'active' && $koi->count > 5));
    }

    public function test_throws_exception_for_undefined_filter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Filter 'undefined' is not defined");

        QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('undefined', MatchModeEnum::IS, 'value'));
    }

    public function test_throws_exception_for_disallowed_match_mode(): void
    {
        $definitions = [
            new FilterDefinition(
                key: 'status',
                type: FilterTypeEnum::SELECT,
                column: 'status',
                allowedMatchModes: [MatchModeEnum::IS], // Only IS allowed
            ),
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Match mode 'is_not' is not allowed for filter 'status'");

        QueryApplicator::for(Koi::query())
            ->withDefinitions($definitions)
            ->applyFilter(FilterValue::make('status', MatchModeEnum::IS_NOT, 'active'));
    }

    public function test_tracks_applied_filters(): void
    {
        $filter1 = FilterValue::make('status', MatchModeEnum::IS, 'active');
        $filter2 = FilterValue::make('count', MatchModeEnum::GREATER_THAN, 5);

        $applicator = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter($filter1)
            ->applyFilter($filter2);

        $this->assertTrue($applicator->hasAppliedFilters());
        $this->assertCount(2, $applicator->getAppliedFilters());
        $this->assertSame($filter1, $applicator->getAppliedFilters()[0]);
        $this->assertSame($filter2, $applicator->getAppliedFilters()[1]);
    }

    public function test_has_applied_filters_returns_false_when_no_filters(): void
    {
        $applicator = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions());

        $this->assertFalse($applicator->hasAppliedFilters());
        $this->assertEmpty($applicator->getAppliedFilters());
    }

    public function test_applies_empty_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('variety', MatchModeEnum::EMPTY, null))
            ->getQuery()
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(['Asagi', 'Shusui'], $result->pluck('name')->sort()->values()->all());
    }

    public function test_applies_not_empty_match_mode(): void
    {
        $result = QueryApplicator::for(Koi::query())
            ->withDefinitions($this->getDefinitions())
            ->applyFilter(FilterValue::make('variety', MatchModeEnum::NOT_EMPTY, null))
            ->getQuery()
            ->get();

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($koi) => $koi->variety !== null));
    }
}
