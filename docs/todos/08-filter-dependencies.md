# TODO: Filter Dependencies and Conditional Visibility

**Priority:** Low
**Status:** Open (Future Enhancement)

## Problem

No way to express:
- Filter dependencies (X depends on Y being set)
- Conditional visibility (show X only when Y has value Z)
- Mutually exclusive filters (X and Y cannot both be active)

## Use Cases

### 1. Hierarchical Dependencies

```php
// Show CityFilter only when CountryFilter is set
// Show DistrictFilter only when CityFilter is set
```

**Real-World Example:**
```php
FilterSelection::make()
    ->where(CountryFilter::class)->is('US')
    // StateFilter should now appear in UI
    ->where(StateFilter::class)->is('CA')
    // CityFilter should now appear in UI
    ->where(CityFilter::class)->is('San Francisco');
```

### 2. Mutually Exclusive Filters

```php
// PriceRangeFilter and PriceTierFilter can't both be active
// DateFilter and DateRangeFilter can't both be active
```

### 3. Conditional Visibility

```php
// Show OrderStatusFilter only when OrderTypeFilter is 'online'
// Hide RefundReasonFilter unless OrderStatusFilter is 'cancelled'
```

## Proposed Solution

### Dependency System

```php
abstract class Filter
{
    /**
     * Filter key this filter depends on.
     */
    public function dependsOn(): ?string
    {
        return null;
    }

    /**
     * Whether this filter should be visible given the current selection.
     */
    public function isVisibleWhen(FilterSelection $selection): bool
    {
        // Check if dependency is satisfied
        if ($dependency = $this->dependsOn()) {
            return $selection->has($dependency);
        }

        return true;
    }

    /**
     * Filters that conflict with this one (mutually exclusive).
     */
    public function conflictsWith(): array
    {
        return [];
    }

    /**
     * Custom visibility logic.
     */
    protected function checkVisibility(FilterSelection $selection): bool
    {
        return true; // Override for complex conditions
    }
}
```

### Example Implementations

#### Hierarchical Dependency

```php
class CityFilter extends SelectFilter
{
    public function dependsOn(): ?string
    {
        return CountryFilter::class;
    }

    public function options(): array
    {
        // Could be dynamic based on CountryFilter value
        return $this->getCitiesForCountry();
    }

    protected function getCitiesForCountry(): array
    {
        // Load cities based on current country selection
    }
}
```

#### Mutually Exclusive

```php
class PriceRangeFilter extends IntegerFilter
{
    public function conflictsWith(): array
    {
        return [PriceTierFilter::class];
    }
}

class PriceTierFilter extends SelectFilter
{
    public function conflictsWith(): array
    {
        return [PriceRangeFilter::class];
    }
}
```

#### Conditional Visibility

```php
class RefundReasonFilter extends SelectFilter
{
    public function isVisibleWhen(FilterSelection $selection): bool
    {
        // Only show if order status is 'cancelled' or 'refunded'
        if (!$selection->has(OrderStatusFilter::class)) {
            return false;
        }

        $statusValue = $selection->get(OrderStatusFilter::class)?->getValue();
        return in_array($statusValue, ['cancelled', 'refunded']);
    }
}
```

### Validation

Add validation to FilterSelection:

```php
class FilterSelection
{
    /**
     * Validate that all dependencies are satisfied.
     */
    public function validateDependencies(array $availableFilters): array
    {
        $errors = [];

        foreach ($this->getAllFilterKeys() as $key) {
            $filter = $this->findFilter($key, $availableFilters);

            // Check dependencies
            if ($dependency = $filter->dependsOn()) {
                if (!$this->has($dependency)) {
                    $errors[] = "{$key} requires {$dependency} to be set";
                }
            }

            // Check conflicts
            foreach ($filter->conflictsWith() as $conflict) {
                if ($this->has($conflict)) {
                    $errors[] = "{$key} conflicts with {$conflict}";
                }
            }
        }

        return $errors;
    }

    /**
     * Get only visible filters based on current selection.
     */
    public function getVisibleFilters(array $availableFilters): array
    {
        return array_filter($availableFilters, function($filter) {
            return $filter->isVisibleWhen($this);
        });
    }
}
```

## UI Integration

This feature is most useful for UI packages:

### filter-livewire

```php
class FilterPanel extends Component
{
    public function getVisibleFiltersProperty(): array
    {
        $selection = $this->buildSelection();
        $allFilters = $this->availableFilters;

        return array_filter($allFilters, function($filter) use ($selection) {
            return $filter->isVisibleWhen($selection);
        });
    }

    public function updatedFilter($value, $key)
    {
        // Validate conflicts
        $selection = $this->buildSelection();
        $errors = $selection->validateDependencies($this->availableFilters);

        if (!empty($errors)) {
            $this->addError('filters', implode(', ', $errors));
        }
    }
}
```

### filter-blade

```blade
@foreach($filters as $filter)
    @if($filter->isVisibleWhen($currentSelection))
        <x-filter-input :filter="$filter" />
    @endif
@endforeach
```

## Implementation Notes

1. **Optional Feature** - All methods have default implementations (returns true/empty)
2. **No Breaking Changes** - Existing filters work without changes
3. **Performance** - Visibility checks should be lightweight
4. **Dynamic Options** - Dependent filters can adjust their options based on parent values

## Alternatives

### Option A: External Dependency Manager

Instead of methods on Filter class, use a separate manager:

```php
FilterDependencyManager::register(CityFilter::class)
    ->dependsOn(CountryFilter::class);

FilterDependencyManager::register(PriceRangeFilter::class)
    ->conflicts(PriceTierFilter::class);
```

**Pro:** Keeps Filter class simpler
**Con:** Configuration is separate from filter definition

### Option B: Attributes (PHP 8+)

```php
#[DependsOn(CountryFilter::class)]
class CityFilter extends SelectFilter
{
    // ...
}

#[ConflictsWith(PriceTierFilter::class)]
class PriceRangeFilter extends IntegerFilter
{
    // ...
}
```

**Pro:** Declarative, clean
**Con:** Less flexible for complex logic

## Related TODOs

- #04 (Database Persistence) - Save which filters were visible when selection was created
- #06 (Debugging Tools) - Show dependency graph in debug output

## Related Files

- `src/Filters/Filter.php` - Add dependency methods
- `src/Selections/FilterSelection.php` - Add validation methods
- Create `src/Dependencies/FilterDependencyManager.php` (if using external manager)
- UI packages would use these methods to show/hide filters
