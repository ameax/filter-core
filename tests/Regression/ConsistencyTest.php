<?php

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\DateUnit;
use Ameax\FilterCore\Exceptions\UnsupportedMatchModeException;
use Ameax\FilterCore\Filters\DateFilter;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Ameax\FilterCore\MatchModes\RegexMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiNameFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Regressions found in the review of October 2026: the same selection must give the
 * same result on every path (QueryApplicator, scope, flat, tolerant) and on every
 * supported database (MySQL, MariaDB, SQL Server).
 */

class RegressionCreatedAtFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }
}

function regressionOrSelection(): FilterSelection
{
    return FilterSelection::makeOr()
        ->where(KoiStatusFilter::class)->is('active')
        ->where(KoiStatusFilter::class)->is('pending');
}

beforeEach(function () {
    $pond = Pond::create(['water_type' => 'fresh', 'capacity' => 100, 'name' => 'P1', 'is_heated' => false]);
    Koi::create(['pond_id' => $pond->id, 'status' => 'active', 'count' => 1, 'name' => 'Rabatt 50% Koi', 'is_active' => true, 'variety' => 'x']);
    Koi::create(['pond_id' => $pond->id, 'status' => 'inactive', 'count' => 10, 'name' => 'Rabatt 500 Koi', 'is_active' => false, 'variety' => 'x']);
    Koi::create(['pond_id' => $pond->id, 'status' => 'pending', 'count' => 3, 'name' => 'a_b [x] Koi', 'is_active' => false, 'variety' => 'x']);
});

it('keeps conditions of the query when an OR selection is applied directly', function () {
    $query = Koi::query()->where('is_active', true);

    $result = QueryApplicator::for($query)
        ->withFilters(Koi::getFilters())
        ->applySelection(regressionOrSelection())
        ->getQuery();

    // Without the parenthesis the SQL read "is_active = 1 AND status = 'active' OR status = 'pending'".
    expect($result->count())->toBe(1);
});

it('honours the OR operator of a flat selection on every path', function () {
    expect(Koi::query()->applySelection(regressionOrSelection())->count())->toBe(2)
        ->and(Koi::query()->applyFilters(regressionOrSelection())->count())->toBe(2)
        ->and(Koi::query()->applySelection(regressionOrSelection(), strict: false)->count())->toBe(2);
});

it('drops unknown filters but keeps OR in tolerant mode', function () {
    $selection = FilterSelection::fromArray(['group' => ['operator' => 'or', 'items' => [
        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
        ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'pending'],
    ]]]);

    expect(Koi::query()->applySelection($selection, strict: false)->count())->toBe(2);
});

it('reads the date range modes without manual registration', function () {
    expect(MatchMode::get('dateRange'))->toBeInstanceOf(DateRangeMatchMode::class)
        ->and(MatchMode::get('date_range'))->toBeInstanceOf(DateRangeMatchMode::class)
        ->and(MatchMode::get('notInDateRange'))->toBeInstanceOf(NotInDateRangeMatchMode::class)
        ->and(MatchMode::get('not_in_date_range'))->toBeInstanceOf(NotInDateRangeMatchMode::class);

    $json = json_encode(['filters' => [['filter' => 'RegressionCreatedAtFilter', 'mode' => 'dateRange', 'value' => DateRangeValue::lastDays(7)->toArray()]]]);
    $selection = FilterSelection::fromJson((string) $json);

    expect($selection->all()[0]->getMatchMode())->toBeInstanceOf(DateRangeMatchMode::class);
});

it('treats LIKE wildcards in the input as plain characters', function (string $mode, string $value, array $expected) {
    $names = QueryApplicator::for(Koi::query())
        ->withFilters([KoiNameFilter::class])
        ->applyFilter(new FilterValue('KoiNameFilter', MatchMode::get($mode), $value))
        ->getQuery()
        ->orderBy('id')
        ->pluck('name')
        ->all();

    expect($names)->toBe($expected);
})->with([
    'percent in contains' => ['contains', '50%', ['Rabatt 50% Koi']],
    'underscore in contains' => ['contains', 'a_b', ['a_b [x] Koi']],
    'bracket in contains' => ['contains', '[x]', ['a_b [x] Koi']],
    'percent in startsWith' => ['startsWith', 'Rabatt 50%', ['Rabatt 50% Koi']],
    'underscore in endsWith' => ['endsWith', '_b [x] Koi', ['a_b [x] Koi']],
    'percent in containsAll' => ['containsAll', 'Rabatt 50%', ['Rabatt 50% Koi']],
    'plain contains still works' => ['contains', 'Rabatt', ['Rabatt 50% Koi', 'Rabatt 500 Koi']],
]);

it('treats the zero date like NULL in date comparisons', function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Only MySQL and MariaDB can store the zero date.');
    }

    Carbon::setTestNow(Carbon::create(2024, 11, 15, 12, 0, 0));
    DB::statement("SET SESSION sql_mode = ''");
    DB::table('kois')->update(['created_at' => '2024-01-01 00:00:00']);
    DB::table('kois')->where('status', 'pending')->update(['created_at' => '0000-00-00 00:00:00']);

    $apply = fn (FilterValue $value) => QueryApplicator::for(Koi::query())
        ->withFilters([RegressionCreatedAtFilter::class])
        ->applyFilter($value)
        ->getQuery()
        ->pluck('status')
        ->sort()
        ->values()
        ->all();

    $olderThan = $apply(new FilterValue('RegressionCreatedAtFilter', new DateRangeMatchMode, DateRangeValue::olderThan(60, DateUnit::DAY)));
    $notInLastWeek = $apply(new FilterValue('RegressionCreatedAtFilter', new NotInDateRangeMatchMode, DateRangeValue::lastDays(7)));

    Carbon::setTestNow();

    expect($olderThan)->toBe(['active', 'inactive'])
        ->and($notInLastWeek)->toBe(['active', 'inactive']);
});

it('rejects regex where the database has none', function () {
    $query = Koi::query();

    if (DB::connection()->getDriverName() !== 'sqlsrv') {
        (new RegexMatchMode)->apply($query, 'name', '^Rabatt');
        expect($query->count())->toBe(2);

        return;
    }

    expect(fn () => (new RegexMatchMode)->apply($query, 'name', '^Rabatt'))
        ->toThrow(UnsupportedMatchModeException::class);
});

/*
 * Characterisation, not a fix: two conditions on the same relation mean "the same
 * related record" on the flat path and "any related record each" on the group path.
 * ameax relies on the flat meaning. Version 2 makes it explicit (RelationScope);
 * until then these tests make sure neither path changes silently.
 */
it('keeps the current relation semantics of both paths', function () {
    $filters = [KoiStatusFilter::via('kois'), KoiCountFilter::via('kois')];
    $selection = FilterSelection::make()
        ->where(KoiStatusFilter::class)->is('active')
        ->where(KoiCountFilter::class)->gt(5);

    $flat = QueryApplicator::for(Pond::query())->withFilters($filters)->applyFilters($selection->all())->getQuery()->count();
    $grouped = QueryApplicator::for(Pond::query())->withFilters($filters)->applySelection($selection)->getQuery()->count();

    expect($flat)->toBe(0)->and($grouped)->toBe(1);
});
