# TODO: Dynamic Filters Not Fully Serializable

**Priority:** Medium
**Status:** Open

## Problem

Dynamic filters can't be fully reconstructed from JSON because there's no `fromArray()` method.

```php
$dynamic = SelectFilter::dynamic('status')
    ->withColumn('status')
    ->withOptions(['a' => 'A', 'b' => 'B']);

// toDefinition() includes options, but can't reconstruct to DynamicSelectFilter
```

## Impact

- Dynamic filters can be serialized to `FilterDefinition` but not back to filter instances
- Limits ability to persist and reload dynamic filter configurations
- Workaround: Store dynamic filter configurations separately and reconstruct manually

## Solution

Add full serialization support with `toArray()` / `fromArray()` methods:

```php
// Serialize
$array = $dynamic->toArray();
// → ['key' => 'status', 'type' => 'select', 'column' => 'status', 'options' => [...]]

// Deserialize
$filter = DynamicSelectFilter::fromArray($array);
// → Fully reconstructed filter with all settings
```

## Implementation Steps

1. Add `toArray()` method to `DynamicFilter` interface
2. Implement in each dynamic filter class:
   - `DynamicSelectFilter`
   - `DynamicIntegerFilter`
   - `DynamicTextFilter`
   - `DynamicBooleanFilter`
3. Add static `fromArray()` factory method to each
4. Include all filter properties (column, label, nullable, meta, options, etc.)
5. Add tests for round-trip serialization

## Related Files

- `src/Filters/Dynamic/DynamicFilter.php` - Interface
- `src/Filters/Dynamic/DynamicSelectFilter.php`
- `src/Filters/Dynamic/DynamicIntegerFilter.php`
- `src/Filters/Dynamic/DynamicTextFilter.php`
- `src/Filters/Dynamic/DynamicBooleanFilter.php`
