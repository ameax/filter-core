# Resolution: check a selection before applying it

A saved selection can outlive the filters it was built from: a filter is removed or
renamed, an option is deleted, a match mode is retired. Applying such a selection
directly throws, or worse, silently ignores a condition and returns more records than
intended.

Resolution checks the whole selection first, at every depth, and tells you what cannot
be applied and where.

## Strict and lenient

```php
use Ameax\FilterCore\Resolution\FilterContext;

// Strict (default): any finding throws SelectionNotApplicableException
$resolved = Account::resolveSelection($selection);

// Lenient: findings are left out and reported
$resolved = Account::resolveSelection($selection, FilterContext::lenient());

$accounts = $resolved->applyTo(Account::query())->get();

if (! $resolved->isComplete()) {
    foreach ($resolved->diagnostics() as $diagnostic) {
        logger()->info('Condition left out', $diagnostic->toArray());
    }
}
```

The caller decides, not the selection. Use strict where a wrong result does harm, for
example a job that deletes or exports records. Use lenient where a person looks at the
result, for example a list that shows a hint.

| | strict | lenient |
|---|---|---|
| condition cannot be applied | `SelectionNotApplicableException` with all findings | left out, finding kept |
| group ends up empty | — | removed |
| nothing left to filter by | — | `hasConditions()` is false, the query is unfiltered |

## What is checked

| `DiagnosticCode` | when |
|---|---|
| `UnknownFilter` | no filter with this key is registered |
| `UnknownMode` | the match mode is unknown (see lenient loading) |
| `ModeNotAllowed` | the filter does not offer this match mode |
| `InvalidValue` | sanitizing, `typedValue()` or the validation rules reject the value, e.g. a deleted option |

Each `Diagnostic` carries the code, the path (item positions from the root group, e.g.
`[1, 0]`), filter key, mode, value, a message and validation details.

## Lenient loading

Loading a selection with an unknown match mode throws by default. With `lenient: true`
the condition is kept as `UnresolvedMatchMode` and written back unchanged on save, so a
user can still open, see and repair it:

```php
$selection = FilterSelection::fromJson($json, lenient: true);
```

The Resolver reports these conditions as `UnknownMode`; they are never applied.

## Context

`FilterContext` carries the policy, the user, a time zone and any attributes the
application needs (tenant, permissions). Filters that implement `ContextAwareFilter`
receive it:

```php
final class AccountOwnerFilter extends SelectFilter implements ContextAwareFilter
{
    public function applyWithContext(Builder|QueryBuilder $query, MatchModeContract $mode, mixed $value, FilterContext $context): bool
    {
        $query->whereIn('team_id', $context->attribute('teamIds', []));

        return false; // continue with the default match mode logic
    }
}

$context = FilterContext::strict()->withUser($user)->withAttribute('teamIds', $user->teamIds());
Account::query()->applySelectionWith($selection, $context)->get();
```

Without a context, `apply()` is used as before.

## Non-strict scope

`applySelection($selection, strict: false)` now resolves leniently: unknown filters,
unknown or disallowed match modes and invalid values are left out at any depth, and the
group operators are kept.
