<?php

use Ameax\FilterCore\Contracts\ContextAwareFilter;
use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Exceptions\SelectionNotApplicableException;
use Ameax\FilterCore\MatchModes\UnresolvedMatchMode;
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Resolution\DiagnosticCode;
use Ameax\FilterCore\Resolution\FilterContext;
use Ameax\FilterCore\Resolution\Resolver;
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Models\Koi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ResolverTenantStatusFilter extends KoiStatusFilter implements ContextAwareFilter
{
    public static ?FilterContext $received = null;

    public function applyWithContext(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value, FilterContext $context): bool
    {
        static::$received = $context;

        // The context narrows the query: here the application's "only active" rule.
        if ($context->attribute('onlyActive') === true) {
            $query->where('is_active', true);
        }

        return false;
    }
}

function resolverData(array $group): FilterSelection
{
    return FilterSelection::fromArray(['group' => $group], lenient: true);
}

function resolverSelection(): FilterSelection
{
    // status = active OR (status = pending AND <unknown filter>) OR count > 5
    return resolverData(['operator' => 'or', 'items' => [
        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'active'],
        ['operator' => 'and', 'items' => [
            ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'pending'],
            ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
        ]],
        ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 5],
    ]]);
}

beforeEach(function () {
    Koi::create(['status' => 'active', 'count' => 1, 'name' => 'a', 'is_active' => true]);
    Koi::create(['status' => 'pending', 'count' => 2, 'name' => 'b', 'is_active' => false]);
    Koi::create(['status' => 'inactive', 'count' => 9, 'name' => 'c', 'is_active' => false]);
    ResolverTenantStatusFilter::$received = null;
});

it('throws in strict mode with every finding and its path', function () {
    try {
        Resolver::for(Koi::getFilters())->resolve(resolverSelection());
        $this->fail('expected SelectionNotApplicableException');
    } catch (SelectionNotApplicableException $e) {
        $all = $e->diagnostics->all();
        expect($all)->toHaveCount(1)
            ->and($all[0]->code)->toBe(DiagnosticCode::UnknownFilter)
            ->and($all[0]->path)->toBe([1, 1])
            ->and($all[0]->filterKey)->toBe('GoneFilter');
    }
});

it('leaves out what cannot be applied in lenient mode, at any depth', function () {
    $resolved = Resolver::for(Koi::getFilters())->resolve(resolverSelection(), FilterContext::lenient());

    // The nested AND group keeps "status = pending" only.
    $statuses = $resolved->applyTo(Koi::query())->orderBy('id')->pluck('status')->all();

    expect($resolved->isComplete())->toBeFalse()
        ->and($resolved->diagnostics()->filterKeys())->toBe(['GoneFilter'])
        ->and($statuses)->toBe(['active', 'pending', 'inactive']);
});

it('removes groups that end up empty', function () {
    $selection = resolverData(['operator' => 'and', 'items' => [
        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'inactive'],
        ['operator' => 'or', 'items' => [
            ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
            ['filter' => 'OtherGoneFilter', 'mode' => 'is', 'value' => 'y'],
        ]],
    ]]);

    $resolved = Resolver::for(Koi::getFilters())->resolve($selection, FilterContext::lenient());

    expect($resolved->selection()->hasNestedGroups())->toBeFalse()
        ->and($resolved->diagnostics())->toHaveCount(2)
        ->and($resolved->applyTo(Koi::query())->pluck('status')->all())->toBe(['inactive']);
});

it('reports when nothing is left to filter by', function () {
    $selection = resolverData(['operator' => 'and', 'items' => [
        ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
    ]]);

    $resolved = Resolver::for(Koi::getFilters())->resolve($selection, FilterContext::lenient());

    expect($resolved->hasConditions())->toBeFalse()
        ->and($resolved->applyTo(Koi::query())->count())->toBe(3);
});

it('keeps an unknown match mode through lenient loading and saving', function () {
    $data = ['filters' => [['filter' => 'KoiStatusFilter', 'mode' => 'retiredMode', 'value' => 'active']]];

    $selection = FilterSelection::fromArray($data, lenient: true);
    $condition = $selection->all()[0];

    expect($condition->getMatchMode())->toBeInstanceOf(UnresolvedMatchMode::class)
        ->and($selection->toArray()['filters'][0]['mode'])->toBe('retiredMode');

    $resolved = Resolver::for(Koi::getFilters())->resolve($selection, FilterContext::lenient());
    expect($resolved->diagnostics()->all()[0]->code)->toBe(DiagnosticCode::UnknownMode);
});

it('still rejects an unknown match mode when loading strictly', function () {
    FilterSelection::fromArray(['filters' => [['filter' => 'KoiStatusFilter', 'mode' => 'retiredMode', 'value' => 'x']]]);
})->throws(InvalidArgumentException::class);

it('reports a match mode the filter does not offer', function () {
    $selection = resolverData(['operator' => 'and', 'items' => [
        ['filter' => 'KoiStatusFilter', 'mode' => 'gt', 'value' => 5],
    ]]);

    $resolved = Resolver::for(Koi::getFilters())->resolve($selection, FilterContext::lenient());

    expect($resolved->diagnostics()->all()[0]->code)->toBe(DiagnosticCode::ModeNotAllowed);
});

it('reports a value the filter no longer accepts, such as a deleted option', function () {
    $selection = resolverData(['operator' => 'and', 'items' => [
        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'deleted-option'],
    ]]);

    $resolved = Resolver::for(Koi::getFilters())->resolve($selection, FilterContext::lenient());
    $diagnostic = $resolved->diagnostics()->all()[0];

    expect($diagnostic->code)->toBe(DiagnosticCode::InvalidValue)
        ->and($diagnostic->details)->toHaveKey('value')
        ->and($diagnostic->toArray())->toMatchArray(['code' => 'invalid_value', 'filter' => 'KoiStatusFilter', 'mode' => 'is']);
});

it('applies a complete selection exactly like applySelection', function () {
    $selection = FilterSelection::makeOr()
        ->where(KoiStatusFilter::class)->is('active')
        ->where(KoiCountFilter::class)->gt(5);

    $resolved = Koi::resolveSelection($selection);

    expect($resolved->isComplete())->toBeTrue()
        ->and($resolved->applyTo(Koi::query())->orderBy('id')->pluck('name')->all())
        ->toBe(Koi::query()->applySelection($selection)->orderBy('id')->pluck('name')->all());
});

it('passes the context to context-aware filters only when one is given', function () {
    $selection = FilterSelection::make()->where(ResolverTenantStatusFilter::class)->any(['active', 'pending']);
    $filters = [ResolverTenantStatusFilter::make()];

    $without = QueryApplicator::for(Koi::query())->withFilters($filters)->applySelection($selection)->getQuery()->count();
    expect(ResolverTenantStatusFilter::$received)->toBeNull();

    $context = FilterContext::strict()->withAttribute('onlyActive', true);
    $with = QueryApplicator::for(Koi::query())->withFilters($filters)->withContext($context)->applySelection($selection)->getQuery()->count();

    expect($without)->toBe(2)
        ->and($with)->toBe(1)
        ->and(ResolverTenantStatusFilter::$received)->toBe($context);
});

it('makes the non-strict scope tolerant for nested groups and deleted options', function () {
    $selection = resolverData(['operator' => 'or', 'items' => [
        ['filter' => 'KoiStatusFilter', 'mode' => 'is', 'value' => 'deleted-option'],
        ['operator' => 'and', 'items' => [
            ['filter' => 'GoneFilter', 'mode' => 'is', 'value' => 'x'],
            ['filter' => 'KoiCountFilter', 'mode' => 'gt', 'value' => 5],
        ]],
    ]]);

    expect(Koi::query()->applySelection($selection, strict: false)->pluck('name')->all())->toBe(['c']);
});

it('applies with a strict context through the scope and throws on findings', function () {
    Koi::query()->applySelectionWith(resolverSelection(), FilterContext::strict())->get();
})->throws(SelectionNotApplicableException::class);
