<?php

use Ameax\FilterCore\Collection\CollectionApplicator;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\MatchModes\DateRangeMatchMode;
use Ameax\FilterCore\MatchModes\NotInDateRangeMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCreatedAtFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\KoiVarietyFilter;
use Ameax\FilterCore\Tests\Filters\PondKoiCreatedAtFilter;
use Ameax\FilterCore\Tests\Filters\PondKoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Carbon\Carbon;

/*
 * Negated match modes across a relation hold for every related record: "is not",
 * "is empty", "none of" and "not in range" mean that no related record matches the
 * positive mode — a record without any related record included. A mode and its
 * negation always add up to all records.
 */

/** @return list<string> sorted names */
function negatedViaQuery(FilterSelection $selection, array $filters, string $model = Pond::class): array
{
    $names = QueryApplicator::for($model::query())->withFilters($filters)->applySelection($selection)->getQuery()->pluck('name')->all();
    sort($names);

    return $names;
}

/** @return list<string> sorted names, through the query and the collection path, which must agree */
function negatedScopedBoth(FilterSelection $selection): array
{
    $filters = [PondKoiStatusFilter::class, PondKoiCreatedAtFilter::class];
    $query = negatedViaQuery($selection, $filters);

    $collection = CollectionApplicator::for(Pond::with('kois')->get())->withFilters($filters)->applySelection($selection)->getCollection()->pluck('name')->all();
    sort($collection);
    expect($collection)->toBe($query);

    return $query;
}

beforeEach(function () {
    // A: an active Gosanke koi from 2023
    // B: an active koi without variety (2025) and an inactive Kawarimono (2023)
    // C: only an inactive koi without variety (2025)
    // D: no koi at all
    $a = Pond::create(['name' => 'A', 'water_type' => 'fresh']);
    $b = Pond::create(['name' => 'B', 'water_type' => 'fresh']);
    $c = Pond::create(['name' => 'C', 'water_type' => 'salt']);
    Pond::create(['name' => 'D', 'water_type' => 'salt']);

    $koi = fn (?Pond $pond, string $name, string $status, ?string $variety, string $created): Koi => Koi::forceCreate([
        'pond_id' => $pond?->id, 'name' => $name, 'status' => $status, 'variety' => $variety,
        'created_at' => Carbon::parse($created), 'updated_at' => Carbon::parse($created),
    ]);
    $koi($a, 'Showa', 'active', 'Gosanke', '2023-05-01');
    $koi($b, 'Kohaku', 'active', null, '2025-05-01');
    $koi($b, 'Sanke', 'inactive', 'Kawarimono', '2023-05-01');
    $koi($c, 'Asagi', 'inactive', null, '2025-05-01');
    $koi(null, 'Ochiba', 'pending', null, '2025-05-01');
});

it('reads "is not" through via() as "no related record is"', function () {
    $filters = [KoiStatusFilter::via('kois')];

    expect(negatedViaQuery(FilterSelection::make()->where(KoiStatusFilter::class)->isNot('active'), $filters))->toBe(['C', 'D'])
        ->and(negatedViaQuery(FilterSelection::make()->where(KoiStatusFilter::class)->is('active'), $filters))->toBe(['A', 'B']);
});

it('reads "is empty" through via() as "no related record has a value", a record without related records included', function () {
    $filters = [KoiVarietyFilter::via('kois')];

    expect(negatedViaQuery(FilterSelection::make()->where(KoiVarietyFilter::class)->empty(), $filters))->toBe(['C', 'D'])
        ->and(negatedViaQuery(FilterSelection::make()->where(KoiVarietyFilter::class)->notEmpty(), $filters))->toBe(['A', 'B']);
});

it('keeps "none of" through via() as "no related record has any of the values"', function () {
    $filters = [KoiStatusFilter::via('kois')];

    expect(negatedViaQuery(FilterSelection::make()->where(KoiStatusFilter::class)->none(['inactive']), $filters))->toBe(['A', 'D']);
});

it('includes a record without the related record for a negated mode on a belongsTo relation', function () {
    $filters = [PondWaterTypeFilter::via('pond')];

    // Ochiba has no pond: it is not in a fresh pond.
    expect(negatedViaQuery(FilterSelection::make()->where(PondWaterTypeFilter::class)->isNot('fresh'), $filters, Koi::class))->toBe(['Asagi', 'Ochiba'])
        ->and(negatedViaQuery(FilterSelection::make()->where(PondWaterTypeFilter::class)->is('fresh'), $filters, Koi::class))->toBe(['Kohaku', 'Sanke', 'Showa']);
});

it('reads negated modes of a scoped filter the same way, in the query and the collection', function () {
    expect(negatedScopedBoth(FilterSelection::make()->where(PondKoiStatusFilter::class)->isNot('active')))->toBe(['C', 'D'])
        ->and(negatedScopedBoth(FilterSelection::make()->where(PondKoiStatusFilter::class)->none(['active'])))->toBe(['C', 'D']);
});

it('reads "not in range" across a relation as "no related record in range"', function () {
    $in2023 = DateRangeValue::between('2023-01-01', '2023-12-31');

    $notIn = FilterSelection::make()->add(new FilterValue('PondKoiCreatedAtFilter', new NotInDateRangeMatchMode, $in2023));
    $in = FilterSelection::make()->add(new FilterValue('PondKoiCreatedAtFilter', new DateRangeMatchMode, $in2023));

    expect(negatedScopedBoth($notIn))->toBe(['C', 'D'])
        ->and(negatedScopedBoth($in))->toBe(['A', 'B']);
});

it('keeps a negated via() filter out of the shared whereHas() of applyFilters()', function () {
    $names = QueryApplicator::for(Pond::query())
        ->withFilters([KoiStatusFilter::via('kois'), KoiVarietyFilter::via('kois')])
        ->applyFilters([
            FilterValue::for(KoiStatusFilter::class)->is('inactive'),
            FilterValue::for(KoiVarietyFilter::class)->empty(),
        ])
        ->getQuery()->pluck('name')->sort()->values()->all();

    // An inactive koi, and no koi with a variety: only C — B's inactive koi is a Kawarimono.
    expect($names)->toBe(['C']);
});

it('adds a mode and its negation up to all records', function (string $filter, string $positive, string $negated, mixed $value) {
    $all = Pond::query()->pluck('name')->sort()->values()->all();
    $filters = [KoiStatusFilter::via('kois'), KoiVarietyFilter::via('kois')];
    $build = fn (string $mode) => FilterSelection::make()->where($filter)->{$mode}(...($value === null ? [] : [$value]));

    $both = array_merge(negatedViaQuery($build($positive), $filters), negatedViaQuery($build($negated), $filters));
    sort($both);

    expect($both)->toBe($all);
})->with([
    'is / is not' => [KoiStatusFilter::class, 'is', 'isNot', 'active'],
    'any / none' => [KoiStatusFilter::class, 'any', 'none', ['inactive']],
    'not empty / empty' => [KoiVarietyFilter::class, 'notEmpty', 'empty', null],
]);

it('runs the own apply() logic of a via() filter inside the relation', function () {
    // DateFilter has its own apply(); it used to run on the ponds table and ignore via().
    $filters = [KoiCreatedAtFilter::via('kois')];
    $in2023 = DateRangeValue::between('2023-01-01', '2023-12-31');

    expect(negatedViaQuery(FilterSelection::make()->add(new FilterValue('KoiCreatedAtFilter', new DateRangeMatchMode, $in2023)), $filters))->toBe(['A', 'B'])
        ->and(negatedViaQuery(FilterSelection::make()->add(new FilterValue('KoiCreatedAtFilter', new NotInDateRangeMatchMode, $in2023)), $filters))->toBe(['C', 'D']);
});
