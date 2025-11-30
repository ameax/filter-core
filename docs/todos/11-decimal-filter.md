# TODO: DecimalFilter Type

**Priority:** High
**Status:** Completed

## Problem

No built-in filter type for decimal/float columns (`DECIMAL`, `FLOAT`, `DOUBLE`). IntegerFilter doesn't handle decimal values correctly.

## Use Cases

- **E-Commerce**: Prices, discounts, tax rates
- **Finance**: Amounts, balances, interest rates
- **Ratings**: 4.5 stars, percentages
- **Measurements**: Weight, distance, temperature

## Proposed Solution

Add `DecimalFilter` base class for filtering decimal/float columns with configurable precision.

### Implementation

```php
namespace Ameax\FilterCore\Filters;

abstract class DecimalFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DECIMAL;
    }

    /**
     * Number of decimal places (default: 2).
     * Override in subclass for different precision.
     */
    public function precision(): int
    {
        return 2;
    }

    /**
     * Whether the value is stored as integer in the database.
     * E.g., 19.99 stored as 1999 (cents).
     * When true, values are multiplied by 10^precision for queries.
     */
    public function storedAsInteger(): bool
    {
        return false;
    }

    /**
     * Convert display value to storage value.
     */
    protected function toStorageValue(float $value): int|float
    {
        if ($this->storedAsInteger()) {
            return (int) round($value * (10 ** $this->precision()));
        }

        return $value;
    }

    public function allowedMatchModes(): array
    {
        return [
            new IsMatchMode(),
            new IsNotMatchMode(),
            new AnyMatchMode(),
            new NoneMatchMode(),
            new GreaterThanMatchMode(),
            new GreaterThanOrEqualMatchMode(),
            new LessThanMatchMode(),
            new LessThanOrEqualMatchMode(),
            new BetweenMatchMode(),
            new EmptyMatchMode(),
            new NotEmptyMatchMode(),
        ];
    }

    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->sanitizeValue($v, $mode), $value);
        }

        // Convert to float
        if (is_string($value)) {
            $value = (float) $value;
        }

        if (is_int($value)) {
            $value = (float) $value;
        }

        // Round to precision
        if (is_float($value)) {
            return round($value, $this->precision());
        }

        return $value;
    }

    public function validationRules(MatchModeContract $mode): array
    {
        if ($mode instanceof BetweenMatchMode) {
            return [
                'value' => ['required', 'array', 'size:2'],
                'value.0' => ['required', 'numeric', 'min:' . $this->min()],
                'value.1' => ['required', 'numeric', 'max:' . $this->max()],
            ];
        }

        if ($mode instanceof AnyMatchMode || $mode instanceof NoneMatchMode) {
            return [
                'value' => ['required', 'array', 'min:1'],
                'value.*' => ['required', 'numeric', 'min:' . $this->min(), 'max:' . $this->max()],
            ];
        }

        if ($mode instanceof EmptyMatchMode || $mode instanceof NotEmptyMatchMode) {
            return [];
        }

        return [
            'value' => ['required', 'numeric', 'min:' . $this->min(), 'max:' . $this->max()],
        ];
    }

    /**
     * Minimum allowed value (override in subclass).
     */
    public function min(): float
    {
        return PHP_FLOAT_MIN;
    }

    /**
     * Maximum allowed value (override in subclass).
     */
    public function max(): float
    {
        return PHP_FLOAT_MAX;
    }

    /**
     * Optional: Type-safe value accessor
     */
    public function typedValue(float|int|string|array $value): float|array
    {
        if (is_array($value)) {
            return array_map(fn($v) => (float) $v, $value);
        }

        return (float) $value;
    }
}
```

### Usage Examples

```php
// Price Filter (stored as decimal in DB)
class PriceFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'price';
    }

    public function label(): string
    {
        return 'Price';
    }

    public function precision(): int
    {
        return 2; // $19.99
    }

    public function min(): float
    {
        return 0.0;
    }

    public function max(): float
    {
        return 999999.99;
    }
}

// Price Filter (stored as integer/cents in DB)
class PriceCentsFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'price'; // DB stores 1999 for $19.99
    }

    public function label(): string
    {
        return 'Price';
    }

    public function precision(): int
    {
        return 2;
    }

    public function storedAsInteger(): bool
    {
        return true; // User enters 19.99 → Query: WHERE price = 1999
    }

    public function min(): float
    {
        return 0.0;
    }

    public function max(): float
    {
        return 999999.99;
    }
}

// Rating Filter
class RatingFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'rating';
    }

    public function label(): string
    {
        return 'Rating';
    }

    public function precision(): int
    {
        return 1; // 4.5 stars
    }

    public function min(): float
    {
        return 0.0;
    }

    public function max(): float
    {
        return 5.0;
    }
}

// Usage
$products = Product::query()
    ->applyFilters([
        // Exact price
        FilterValue::for(PriceFilter::class)->is(19.99),

        // Price range
        FilterValue::for(PriceFilter::class)->between(10.00, 99.99),

        // Minimum price
        FilterValue::for(PriceFilter::class)->gte(50.00),

        // Rating 4+ stars
        FilterValue::for(RatingFilter::class)->gte(4.0),

        // Specific ratings
        FilterValue::for(RatingFilter::class)->any([4.5, 5.0]),
    ])
    ->get();
```

### Percentage Example

```php
class DiscountFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'discount_percentage';
    }

    public function precision(): int
    {
        return 2; // 15.50%
    }

    public function min(): float
    {
        return 0.0;
    }

    public function max(): float
    {
        return 100.0;
    }
}

// Products with 10-20% discount
$products = Product::query()
    ->applyFilter(FilterValue::for(DiscountFilter::class)->between(10.0, 20.0))
    ->get();
```

### Dynamic Filter Support

```php
$priceFilter = DecimalFilter::dynamic('price')
    ->withColumn('price')
    ->withLabel('Price')
    ->withPrecision(2)
    ->withMin(0.0)
    ->withMax(999999.99);

$selection = FilterSelection::make()
    ->where($priceFilter)->between(10.00, 99.99);
```

## Float vs Decimal Precision

```php
// Problem with floats
0.1 + 0.2 === 0.3 // false (0.30000000000000004)

// Solution: round to precision
round(0.1 + 0.2, 2) === 0.3 // true

// DecimalFilter handles this automatically
$filter->sanitizeValue(0.30000000000000004, $mode); // 0.30
```

## Database Considerations

```php
// MySQL DECIMAL
Schema::create('products', function (Blueprint $table) {
    $table->decimal('price', 8, 2); // 8 digits, 2 after decimal
    $table->decimal('rating', 3, 1); // 3 digits, 1 after decimal (5.0)
});

// Precision should match DB column precision
class PriceFilter extends DecimalFilter {
    public function precision(): int {
        return 2; // Matches DECIMAL(8,2)
    }
}
```

## Implementation Steps

1. Add `FilterTypeEnum::DECIMAL` case
2. Create `DecimalFilter` base class with `storedAsInteger()` support
3. Add `DynamicDecimalFilter` with precision/min/max/storedAsInteger options
4. Add comprehensive tests:
   - Decimal sanitization (string, int, float, null)
   - Precision rounding
   - All match modes
   - Between with decimal range
   - Min/max validation
   - Float precision edge cases
   - **storedAsInteger conversion (19.99 → 1999)**
   - Dynamic filter
5. Add to documentation with E-Commerce examples

## Related Files

- `src/Filters/DecimalFilter.php` (NEW)
- `src/Filters/Dynamic/DynamicDecimalFilter.php` (NEW)
- `src/Enums/FilterTypeEnum.php` (UPDATE)
- Match modes reused from IntegerFilter

## Notes

- Uses `numeric` validation (allows integers and decimals)
- Automatically rounds to specified precision
- Configurable min/max for validation
- Works with MySQL DECIMAL, FLOAT, DOUBLE columns
- Handles float precision issues via rounding
- Type-safe with `typedValue()` returning float
- Same match modes as IntegerFilter
- **`storedAsInteger()` support**: For columns that store decimals as integers (e.g., cents). Converts user input (19.99) to storage format (1999) automatically via `toStorageValue()`
