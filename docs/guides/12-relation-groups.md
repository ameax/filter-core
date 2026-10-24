# Relation groups: several conditions on the same related record

A relation filter (`via()`, see [Relation Filters](05-relation-filters.md)) asks "is
there a related record with X". Two of them ask twice, and each may find a different
record:

```php
// Accounts with SOME contact named Miller and SOME contact who is a buyer —
// possibly two different contacts
$selection = FilterSelection::make()
    ->where(ContactNameFilter::class)->contains('Miller')
    ->where(ContactPositionFilter::class)->is('buyer');
```

A relation group asks once, for one record that meets all its conditions:

```php
// Accounts with a contact who is named Miller AND is a buyer
$selection = FilterSelection::make()->whereHas('contacts', function (FilterGroup $contact) {
    $contact->where(ContactNameFilter::class)->contains('Miller');
    $contact->where(ContactPositionFilter::class)->is('buyer');
});
```

It is applied as one `whereHas('contacts', …)` with all conditions inside.

## Modes

| | method | meaning |
|---|---|---|
| has | `whereHas($relation, $callback)`, `FilterGroup::has()` | some related record matches all conditions of the group |
| doesn't have | `whereDoesntHave($relation, $callback)`, `FilterGroup::doesntHave()` | no related record matches them; records without any related record count too |
| has none | `whereHasNone($relation)`, `FilterGroup::hasNone()` | no related record at all; the group takes no conditions |

The operator of the group works as usual: pass `GroupOperatorEnum::OR` for "a contact
who is a buyer OR works in purchasing". Groups nest, also inside relation groups.

## Filters that belong to a relation

A filter whose columns live on the related model declares the relation:

```php
class ContactPositionFilter extends SelectFilter
{
    public function relationScope(): ?string
    {
        return 'contacts';
    }

    public function column(): string
    {
        return 'position';
    }
}
```

- inside a group for `contacts` it applies to the contact directly
- anywhere else it wraps itself in `whereHas('contacts', …)` — it never runs against the
  wrong table, also when it has its own `apply()` logic
- inside a group for another relation the resolver reports it (`OutOfScope`)

`via()` stays for registering an existing filter of the related model on the parent
model. Inside a group for the same relation, a `via()` filter does not add a second
`whereHas()`.

## Stored form

```json
{
  "group": {
    "operator": "and",
    "items": [
      {
        "operator": "and",
        "relation": "contacts",
        "relation_mode": "has",
        "items": [
          {"filter": "ContactNameFilter", "mode": "contains", "value": "Miller"},
          {"filter": "ContactPositionFilter", "mode": "is", "value": "buyer"}
        ]
      },
      {"operator": "and", "relation": "invoices", "relation_mode": "has_none", "items": []}
    ]
  }
}
```

Groups without `relation` are stored as before.

## Resolution

The resolver keeps the relation of every group (see [Resolution](11-resolution.md)).

- lenient: a condition that cannot be applied is left out; a group that ends up empty
  is removed
- restrictive: the condition matches nothing. Inside a "doesn't have" group that would
  turn the group into "matches every record", so the whole group matches nothing instead

## Collections

`CollectionApplicator` gives the same result in memory: it reads the related records
from the item (`data_get($item, $relation)`, e.g. an eager-loaded relation) and applies
the group to them.

## Requirements

Relation groups and scoped filters need an Eloquent query (`whereHas()`); on a plain
query builder they throw `InvalidArgumentException`.
