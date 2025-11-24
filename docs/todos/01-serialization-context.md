# TODO: Serialization Loses Context

**Priority:** High
**Status:** Open (Partially Resolved)

## Problem

When saving FilterSelections to JSON, filter metadata is lost. On deserialization, we can't distinguish between static filter classes and dynamic filter keys.

```php
// Saved JSON
{"filters": [{"filter": "status", "mode": "is", "value": "active"}]}

// Is "status" a class StatusFilter or dynamic key "status"? Unknown!
```

## Impact

API endpoints can't validate if modes are allowed or if values are valid without the original filter class/instance.

## Possible Solutions

### Option A: Include Metadata in JSON

```json
{
  "filters": [{
    "filter": "StatusFilter",
    "mode": "is",
    "value": "active",
    "_meta": {
      "type": "static",
      "class": "App\\Filters\\StatusFilter"
    }
  }]
}
```

### Option B: Filter Registry

```php
// Register filters globally
FilterRegistry::register('StatusFilter', StatusFilter::class);
FilterRegistry::resolve('StatusFilter'); // → StatusFilter instance

// Or per-model (already supported via filterResolver)
User::getFilterByKey('StatusFilter'); // ✓ Already works
```

## Current Status

**Partially solved** - Model-based validation via `validateSelection()` exists, but global registry doesn't.

## Related Files

- `src/Selections/FilterSelection.php` - JSON serialization
- `src/Concerns/Filterable.php` - Model-based validation with `validateSelection()`, `getFilterByKey()`
