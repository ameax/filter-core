<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

use Ameax\FilterCore\Data\FilterDefinition;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\RelationModeEnum;
use Ameax\FilterCore\Exceptions\FilterValidationException;
use Ameax\FilterCore\Exceptions\SelectionNotApplicableException;
use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\MatchModes\UnresolvedMatchMode;
use Ameax\FilterCore\Query\ValuePreparer;
use Ameax\FilterCore\Selections\FilterGroup;
use Ameax\FilterCore\Selections\FilterSelection;

/**
 * Checks a whole selection against the registered filters before anything is applied.
 *
 * Walks every group, however deeply nested, and checks each condition: is the filter
 * known, is the match mode known and offered by the filter, does the value pass the
 * filter's sanitizing, type check and validation (e.g. an option that was deleted).
 *
 * - Strict: any finding throws SelectionNotApplicableException with all findings.
 * - Lenient: the condition is left out, groups that end up empty are removed, and the
 *   findings are kept on the result for the caller to show or log.
 * - Restrictive: the condition matches nothing (NeverFilter); findings are kept. Inside
 *   a "doesn't have" relation group that would turn into "matches all", so the whole
 *   group matches nothing instead.
 *
 * Relation groups keep their relation. A filter with a relation scope that stands in a
 * group for another relation is a finding of its own (OutOfScope).
 *
 * What resolves is safe to apply: the applicators find nothing left to reject.
 */
final class Resolver
{
    /** @var array<string, Filter> */
    private array $filters = [];

    /** @var array<string, FilterDefinition> */
    private array $definitions = [];

    private bool $restrictive = false;

    /**
     * @param  array<class-string<Filter>|Filter>  $filters
     */
    public function __construct(array $filters)
    {
        foreach ($filters as $filter) {
            $instance = is_string($filter) ? $filter::make() : $filter;
            $key = $instance->resolveKey();
            $this->filters[$key] = $instance;
            $this->definitions[$key] = $instance->toDefinition();
        }
    }

    /**
     * @param  array<class-string<Filter>|Filter>  $filters
     */
    public static function for(array $filters): self
    {
        return new self($filters);
    }

    /**
     * @throws SelectionNotApplicableException in strict mode when a condition cannot be applied
     */
    public function resolve(FilterSelection $selection, ?FilterContext $context = null): ResolvedSelection
    {
        $context ??= FilterContext::strict();
        $diagnostics = new Diagnostics;
        $this->restrictive = $context->policy === ResolutionPolicy::Restrictive;

        $root = $this->resolveGroup($selection->getGroup(), [], $diagnostics, null)
            ?? $selection->getGroup()->emptyCopy();

        if ($context->isStrict() && ! $diagnostics->isEmpty()) {
            throw new SelectionNotApplicableException($diagnostics);
        }

        return new ResolvedSelection(
            $selection->withGroup($root),
            $diagnostics,
            [...array_values($this->filters), new NeverFilter],
            $context,
        );
    }

    /**
     * @param  list<int>  $path
     * @param  string|null  $relation  the relation whose records the group filters
     */
    private function resolveGroup(FilterGroup $group, array $path, Diagnostics $diagnostics, ?string $relation): ?FilterGroup
    {
        $resolved = $group->emptyCopy();
        $relation = $group->getRelation() ?? $relation;
        $findings = $diagnostics->count();

        foreach ($group->getItems() as $index => $item) {
            $itemPath = [...$path, $index];

            if ($item instanceof FilterGroup) {
                $child = $this->resolveGroup($item, $itemPath, $diagnostics, $relation);
                if ($child !== null) {
                    $resolved->addGroup($child);
                }

                continue;
            }

            $diagnostic = $this->check($item, $itemPath, $relation);
            if ($diagnostic !== null) {
                $diagnostics->add($diagnostic);
                if ($this->restrictive) {
                    $resolved->add(NeverFilter::condition());
                }

                continue;
            }

            $resolved->add($item);
        }

        // Negated: matching nothing inside would match everything outside.
        if ($this->restrictive && $diagnostics->count() > $findings
            && $group->getRelationMode() === RelationModeEnum::DOESNT_HAVE && $group->isRelationGroup()) {
            return FilterGroup::and()->add(NeverFilter::condition());
        }

        return $resolved->isEmpty() ? null : $resolved;
    }

    /**
     * @param  list<int>  $path
     */
    private function check(FilterValue $filterValue, array $path, ?string $relation): ?Diagnostic
    {
        $key = $filterValue->getFilterKey();
        $mode = $filterValue->getMatchMode();
        $value = $filterValue->getValue();

        $diagnose = fn (DiagnosticCode $code, string $message, array $details = []): Diagnostic => new Diagnostic(
            $code, $path, $key, $mode->key(), $value, $message, $details
        );

        $filter = $this->filters[$key] ?? null;
        if ($filter === null) {
            return $diagnose(DiagnosticCode::UnknownFilter, "Filter '{$key}' is not defined");
        }

        $scope = $filter->relationScope();
        if ($scope !== null && $relation !== null && $scope !== $relation) {
            return $diagnose(DiagnosticCode::OutOfScope, "Filter '{$key}' belongs to relation '{$scope}', not '{$relation}'");
        }

        if ($mode instanceof UnresolvedMatchMode) {
            return $diagnose(DiagnosticCode::UnknownMode, "Match mode '{$mode->key()}' is unknown");
        }

        if (! $this->definitions[$key]->isMatchModeAllowed($mode)) {
            return $diagnose(DiagnosticCode::ModeNotAllowed, "Match mode '{$mode->key()}' is not allowed for filter '{$key}'");
        }

        try {
            ValuePreparer::prepare($filter, $key, $mode, $value);
        } catch (FilterValidationException $e) {
            return $diagnose(DiagnosticCode::InvalidValue, $e->getMessage(), $e->getErrors());
        }

        return null;
    }
}
