<?php

use Ameax\FilterCore\Collection\CollectionApplicator;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Enums\RelationModeEnum;
use Ameax\FilterCore\Exceptions\SelectionNotApplicableException;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Resolution\DiagnosticCode;
use Ameax\FilterCore\Resolution\FilterContext;
use Ameax\FilterCore\Resolution\Resolver;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\PondKoiCountFilter;
use Ameax\FilterCore\Tests\Filters\PondKoiNameFilter;
use Ameax\FilterCore\Tests\Filters\PondKoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Ameax\FilterCore\Tests\Models\Pond;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/*
 * Relation groups: the conditions of a group bound to a relation hold for one and the
 * same related record. Every case runs through the query and the collection path,
 * which must agree.
 */

function relationGroupFilters(): array
{
    return [PondWaterTypeFilter::class, PondKoiStatusFilter::class, PondKoiCountFilter::class, PondKoiNameFilter::class];
}

/** @return list<string> pond names, sorted */
function relationGroupQuery(FilterSelection $selection): array
{
    $names = QueryApplicator::for(Pond::query())
        ->withFilters(relationGroupFilters())
        ->applySelection($selection)
        ->getQuery()
        ->pluck('name')
        ->all();
    sort($names);

    return $names;
}

/** @return list<string> pond names, sorted */
function relationGroupCollection(FilterSelection $selection): array
{
    $names = CollectionApplicator::for(Pond::with('kois')->get())
        ->withFilters(relationGroupFilters())
        ->applySelection($selection)
        ->getCollection()
        ->pluck('name')
        ->all();
    sort($names);

    return $names;
}

function relationGroupBoth(FilterSelection $selection): array
{
    $query = relationGroupQuery($selection);
    expect(relationGroupCollection($selection))->toBe($query);

    return $query;
}

beforeEach(function () {
    // A: one koi that is active with 10      → matches "active AND > 5" on the same koi
    // B: an active koi with 2, an inactive with 9 → each condition on a different koi
    // C: only an inactive koi with 1
    // D: no koi at all
    $a = Pond::create(['name' => 'A', 'water_type' => 'fresh']);
    $b = Pond::create(['name' => 'B', 'water_type' => 'fresh']);
    $c = Pond::create(['name' => 'C', 'water_type' => 'salt']);
    Pond::create(['name' => 'D', 'water_type' => 'salt']);

    Koi::create(['pond_id' => $a->id, 'name' => 'Showa', 'status' => 'active', 'count' => 10]);
    Koi::create(['pond_id' => $b->id, 'name' => 'Kohaku', 'status' => 'active', 'count' => 2]);
    Koi::create(['pond_id' => $b->id, 'name' => 'Sanke', 'status' => 'inactive', 'count' => 9]);
    Koi::create(['pond_id' => $c->id, 'name' => 'Asagi', 'status' => 'inactive', 'count' => 1]);
});

it('applies the conditions of a relation group to the same related record', function () {
    $selection = FilterSelection::make()->whereHas('kois', function (FilterGroup $koi) {
        $koi->where(PondKoiStatusFilter::class)->is('active');
        $koi->where(PondKoiCountFilter::class)->gt(5);
    });

    expect(relationGroupBoth($selection))->toBe(['A']);
});

it('keeps the relation of a group for a plain subgroup nested in it', function () {
    // "active AND (count > 8 OR name Asagi)" on one koi: only Showa (active, 10).
    // Per koi instead of per pond: B would match too (Kohaku active, Sanke 9).
    $selection = FilterSelection::make()->whereHas('kois', function (FilterGroup $koi) {
        $koi->where(PondKoiStatusFilter::class)->is('active');
        $koi->orWhere(function (FilterGroup $either) {
            $either->where(PondKoiCountFilter::class)->gt(8);
            $either->where(PondKoiNameFilter::class)->is('Asagi');
        });
    });

    expect(relationGroupBoth($selection))->toBe(['A']);
});

it('applies scoped filters outside a group each on its own related record', function () {
    $selection = FilterSelection::make()
        ->where(PondKoiStatusFilter::class)->is('active')
        ->where(PondKoiCountFilter::class)->gt(5);

    expect(relationGroupBoth($selection))->toBe(['A', 'B']);
});

it('finds records that have no matching related record, including those without any', function () {
    $selection = FilterSelection::make()->whereDoesntHave('kois', function (FilterGroup $koi) {
        $koi->where(PondKoiStatusFilter::class)->is('active');
    });

    expect(relationGroupBoth($selection))->toBe(['C', 'D']);
});

it('finds records without any related record', function () {
    expect(relationGroupBoth(FilterSelection::make()->whereHasNone('kois')))->toBe(['D']);
});

it('combines a relation group with conditions on the record itself and OR inside the group', function () {
    $selection = FilterSelection::make()
        ->where(PondWaterTypeFilter::class)->is('fresh')
        ->whereHas('kois', function (FilterGroup $koi) {
            $koi->where(PondKoiCountFilter::class)->gt(8);
            $koi->where(PondKoiStatusFilter::class)->is('pending');
        }, GroupOperatorEnum::OR);

    expect(relationGroupBoth($selection))->toBe(['A', 'B']);
});

it('runs custom query logic of a scoped filter on the related table', function () {
    $selection = FilterSelection::make()->whereHas('kois', function (FilterGroup $koi) {
        $koi->where(PondKoiNameFilter::class)->is('Sanke');
        $koi->where(PondKoiCountFilter::class)->gt(5);
    });

    expect(relationGroupQuery($selection))->toBe(['B'])
        ->and(relationGroupQuery(FilterSelection::make()->where(PondKoiNameFilter::class)->is('Showa')))->toBe(['A']);
});

it('serializes a relation group and reads it back', function () {
    $selection = FilterSelection::make()
        ->whereHas('kois', function (FilterGroup $koi) {
            $koi->where(PondKoiStatusFilter::class)->is('active');
        })
        ->whereHasNone('ponds');

    $data = json_decode($selection->toJson(), true);
    $group = $data['group']['items'][0];
    $restored = FilterSelection::fromJson($selection->toJson());
    $first = $restored->getGroup()->getItems()[0];

    expect($group['relation'])->toBe('kois')
        ->and($group['relation_mode'])->toBe('has')
        ->and($data['group']['items'][1])->toMatchArray(['relation' => 'ponds', 'relation_mode' => 'has_none', 'items' => []])
        ->and($first->getRelation())->toBe('kois')
        ->and($first->getRelationMode())->toBe(RelationModeEnum::HAS)
        ->and($restored->getGroup()->getItems()[1]->isEmpty())->toBeFalse()
        ->and(json_decode($restored->toJson(), true))->toBe($data);
});

it('takes no conditions in a has-none group', function () {
    FilterGroup::hasNone('kois')->add(PondKoiStatusFilter::value()->is('active'));
})->throws(LogicException::class);

it('keeps the relation of a group through resolution and drops what cannot be applied', function () {
    $selection = FilterSelection::fromArray(['group' => ['operator' => 'and', 'items' => [
        ['operator' => 'and', 'relation' => 'kois', 'relation_mode' => 'has', 'items' => [
            ['filter' => 'PondKoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
            ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
        ]],
    ]]], lenient: true);

    $resolved = Resolver::for(relationGroupFilters())->resolve($selection, FilterContext::lenient());
    $group = $resolved->selection()->getGroup()->getItems()[0];

    expect($group->getRelation())->toBe('kois')
        ->and($resolved->diagnostics()->filterKeys())->toBe(['GoneFilter'])
        ->and($resolved->applyTo(Pond::query())->orderBy('name')->pluck('name')->all())->toBe(['A', 'B']);
});

it('reports a scoped filter in a group for another relation under every policy', function () {
    // A koi filter (scope "kois") inside a group bound to a koi's pond.
    $selection = FilterSelection::make()->whereHas('pond', function (FilterGroup $group) {
        $group->where(PondKoiStatusFilter::class)->is('active');
    });
    $resolver = Resolver::for([...relationGroupFilters(), PondWaterTypeFilter::via('pond')]);

    expect(fn () => $resolver->resolve($selection, FilterContext::strict()))->toThrow(SelectionNotApplicableException::class, 'PondKoiStatusFilter');

    $lenient = $resolver->resolve($selection, FilterContext::lenient());
    $restrictive = $resolver->resolve($selection, FilterContext::restrictive());

    expect($lenient->diagnostics()->all()[0]->toArray())->toBe([
        'code' => 'out_of_scope', 'path' => [0, 0], 'filter' => 'PondKoiStatusFilter', 'mode' => 'is', 'value' => 'active',
        'message' => "Filter 'PondKoiStatusFilter' belongs to relation 'kois', not 'pond'", 'details' => [],
    ])
        // lenient: the group is left out, so every koi; restrictive: no koi has such a pond
        ->and($lenient->selection()->hasFilters())->toBeFalse()
        ->and($lenient->applyTo(Koi::query())->count())->toBe(4)
        ->and($restrictive->diagnostics()->all()[0]->code)->toBe(DiagnosticCode::OutOfScope)
        ->and($restrictive->applyTo(Koi::query())->count())->toBe(0);
});

it('never widens a negated relation group under the restrictive policy', function () {
    $selection = FilterSelection::fromArray(['group' => ['operator' => 'and', 'items' => [
        ['operator' => 'and', 'relation' => 'kois', 'relation_mode' => 'doesnt_have', 'items' => [
            ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
        ]],
    ]]], lenient: true);

    $restrictive = Resolver::for(relationGroupFilters())->resolve($selection, FilterContext::restrictive());

    // "Has no koi matching <nothing>" would be every pond.
    expect($restrictive->applyTo(Pond::query())->count())->toBe(0);
});

it('needs an Eloquent query for a relation group', function () {
    $selection = FilterSelection::make()->whereHasNone('kois');

    QueryApplicator::for(Pond::query()->toBase())->withFilters(relationGroupFilters())->applySelection($selection);
})->throws(InvalidArgumentException::class);

it('does not nest a second whereHas for a via() filter inside a group for the same relation', function () {
    $applicator = QueryApplicator::for(Koi::query())->withFilters([PondWaterTypeFilter::via('pond')]);
    $selection = FilterSelection::make()->whereHas('pond', function (FilterGroup $pond) {
        $pond->where(PondWaterTypeFilter::class)->is('fresh');
    });

    $query = $applicator->applySelection($selection)->getQuery();
    $names = $query->orderBy('name')->pluck('name')->all();

    expect($names)->toBe(['Kohaku', 'Sanke', 'Showa'])
        ->and(substr_count(strtolower($query->toSql()), 'exists'))->toBe(1);
});

/** A stored group whose relation is a model method with side effects. */
function relationGroupHostile(): FilterSelection
{
    return FilterSelection::fromArray(['group' => ['operator' => 'and', 'items' => [
        ['operator' => 'and', 'relation' => 'kois.truncate', 'relation_mode' => 'has_none', 'items' => []],
    ]]]);
}

it('rejects a relation group for a relation no registered filter declares, before calling it', function () {
    $kois = Koi::count();

    expect(fn () => relationGroupQuery(relationGroupHostile()))->toThrow(InvalidArgumentException::class, 'kois.truncate')
        ->and(fn () => relationGroupCollection(relationGroupHostile()))->toThrow(InvalidArgumentException::class, 'kois.truncate')
        ->and(Koi::count())->toBe($kois);
});

it('reports an undeclared relation in resolution under every policy', function () {
    $resolver = Resolver::for(relationGroupFilters());

    expect(fn () => $resolver->resolve(relationGroupHostile(), FilterContext::strict()))->toThrow(SelectionNotApplicableException::class);

    $lenient = $resolver->resolve(relationGroupHostile(), FilterContext::lenient());
    $restrictive = $resolver->resolve(relationGroupHostile(), FilterContext::restrictive());

    expect($lenient->diagnostics()->all()[0]->code)->toBe(DiagnosticCode::UnknownRelation)
        ->and($lenient->applyTo(Pond::query())->count())->toBe(4)
        ->and($restrictive->applyTo(Pond::query())->count())->toBe(0)
        ->and(Koi::count())->toBe(4);
});

it('keeps a relation group at the root through serialization', function () {
    $has = FilterSelection::make()->withGroup(FilterGroup::has('kois')
        ->add(PondKoiStatusFilter::value()->is('active'))
        ->add(PondKoiCountFilter::value()->gt(5)));
    $hasNone = FilterSelection::make()->withGroup(FilterGroup::hasNone('kois'));

    expect(relationGroupQuery(FilterSelection::fromJson($has->toJson())))->toBe(['A'])
        ->and(relationGroupQuery(FilterSelection::fromJson($hasNone->toJson())))->toBe(['D'])
        ->and(FilterSelection::fromJson($has->toJson())->getGroup()->getRelation())->toBe('kois');
});

it('reads "none of" on a scoped filter outside a group as "has no such related record", like via()', function () {
    $scoped = FilterSelection::make()->where(PondKoiStatusFilter::class)->none(['active']);
    $inGroup = FilterSelection::make()->whereHas('kois', fn (FilterGroup $koi) => $koi->where(PondKoiStatusFilter::class)->none(['active']));

    // C has only an inactive koi, D none at all; B also has an active one.
    expect(relationGroupBoth($scoped))->toBe(['C', 'D'])
        // Inside the group it is a condition on the one koi: a koi that is not active.
        ->and(relationGroupBoth($inGroup))->toBe(['B', 'C']);
});

it('keeps the relation of a root group when a filter is removed', function () {
    $selection = FilterSelection::make()->withGroup(FilterGroup::has('kois')
        ->add(PondKoiStatusFilter::value()->is('active'))
        ->add(PondKoiCountFilter::value()->gt(5))
        ->add(PondKoiNameFilter::value()->is('Kohaku')));

    $selection->remove(PondKoiNameFilter::class);

    expect($selection->getGroup()->getRelation())->toBe('kois')
        ->and(relationGroupBoth($selection))->toBe(['A']);
});

it('explains relation groups with their relation and mode', function () {
    $selection = FilterSelection::make()
        ->whereHas('kois', fn (FilterGroup $koi) => $koi->where(PondKoiStatusFilter::class)->is('active'))
        ->whereDoesntHave('kois', fn (FilterGroup $koi) => $koi->where(PondKoiCountFilter::class)->gt(5))
        ->whereHasNone('kois');

    expect($selection->explain())->toBe(
        "(HAS kois WHERE (PondKoiStatusFilter IS 'active')) AND (DOESNT HAVE kois WHERE (PondKoiCountFilter GT 5)) AND (HAS NO kois)"
    );
});

it('applies a relation group at the root through applyFilters() like applySelection()', function () {
    // A model that declares the koi filters, so the relation is allowed.
    $pond = new class extends Pond
    {
        protected $table = 'ponds';

        protected static function filterResolver(): Closure
        {
            return fn (): array => relationGroupFilters();
        }

        public function kois(): HasMany
        {
            return $this->hasMany(Koi::class, 'pond_id');
        }
    };
    $hasNone = FilterSelection::make()->withGroup(FilterGroup::hasNone('kois'));
    $doesntHave = FilterSelection::make()->withGroup(FilterGroup::doesntHave('kois')
        ->add(PondKoiStatusFilter::value()->is('active')));
    $names = fn (FilterSelection $selection): array => $pond::query()->applyFilters($selection)->orderBy('name')->pluck('name')->all();

    // The flat path of applyFilters() must not drop the relation of the root group.
    expect($names($hasNone))->toBe(['D'])
        ->and($names($doesntHave))->toBe(['C', 'D']);
});

it('keeps relation data a lenient read cannot resolve and reports it, like an unknown match mode', function () {
    $cases = [
        'empty relation' => [['relation' => '', 'relation_mode' => 'has_none'], "The relation of a group must be a non-empty string, got ''"],
        'relation not a string' => [['relation' => ['kois'], 'relation_mode' => 'has'], 'The relation of a group must be a non-empty string, got ["kois"]'],
        'unknown mode' => [['relation' => 'kois', 'relation_mode' => 'sometimes'], "Relation mode 'sometimes' is unknown"],
    ];
    foreach ($cases as $label => [$relationData, $reason]) {
        $json = json_encode(['group' => ['operator' => 'and', 'items' => [
            $relationData + ['operator' => 'and', 'items' => []],
            ['filter' => PondWaterTypeFilter::key(), 'mode' => 'is', 'value' => 'salt'],
        ]]]);

        // Strict: one clear exception instead of ValueError or "Array to string conversion".
        expect(fn () => FilterSelection::fromJson($json))->toThrow(InvalidArgumentException::class, $reason, $label);

        $selection = FilterSelection::fromJson($json, lenient: true);
        $resolver = Resolver::for(relationGroupFilters());
        $lenient = $resolver->resolve($selection, FilterContext::lenient());
        $restrictive = $resolver->resolve($selection, FilterContext::restrictive());

        expect($selection->getGroup()->getItems()[0]->isRelationGroup())->toBeTrue($label)
            // survives loading and saving as given
            ->and(FilterSelection::fromJson($selection->toJson(), lenient: true)->toArray())->toBe($selection->toArray(), $label)
            // is not applied as it stands
            ->and(fn () => relationGroupQuery($selection))->toThrow(LogicException::class)
            ->and(fn () => relationGroupCollection($selection))->toThrow(LogicException::class)
            ->and(fn () => $resolver->resolve($selection))->toThrow(SelectionNotApplicableException::class)
            // lenient: the group is left out, restrictive: it matches nothing
            ->and($lenient->diagnostics()->count())->toBe(1, $label)
            ->and($lenient->diagnostics()->all()[0]->toArray())->toBe([
                'code' => 'unresolved_relation', 'path' => [0], 'filter' => '',
                'mode' => $relationData['relation_mode'], 'value' => $relationData['relation'],
                'message' => $reason, 'details' => [],
            ], $label)
            ->and(relationGroupBoth($lenient->selection()))->toBe(['C', 'D'], $label)
            ->and($restrictive->applyTo(Pond::query())->count())->toBe(0, $label);
    }
});

it('loads a relation the collection items do not have yet, in one query and without lazy loading', function () {
    $relationGroup = FilterSelection::make()->whereHas('kois', fn (FilterGroup $koi) => $koi->where(PondKoiStatusFilter::class)->is('active'));
    $scoped = FilterSelection::make()->where(PondKoiStatusFilter::class)->none(['active']);
    $names = function (FilterSelection $selection): array {
        $names = CollectionApplicator::for(Pond::all())->withFilters(relationGroupFilters())->applySelection($selection)->getCollection()->pluck('name')->all();
        sort($names);

        return $names;
    };

    Model::preventLazyLoading(true);
    DB::enableQueryLog();
    try {
        DB::flushQueryLog();
        expect($names($relationGroup))->toBe(['A', 'B'])
            ->and(DB::getQueryLog())->toHaveCount(2) // ponds, then their kois in one go
            ->and($names($scoped))->toBe(['C', 'D']);
    } finally {
        Model::preventLazyLoading(false);
        DB::disableQueryLog();
    }
});
