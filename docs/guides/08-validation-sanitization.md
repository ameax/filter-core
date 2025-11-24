# Validation & Sanitization

Filter-core automatically sanitizes input values and validates them against filter rules.

## Value Sanitization

Each filter type automatically transforms input values to the expected type.

### BooleanFilter Sanitization

Converts string representations to boolean:

| Input | Output |
|-------|--------|
| `true`, `'true'`, `'1'`, `'yes'`, `'on'` | `true` |
| `false`, `'false'`, `'0'`, `'no'`, `'off'` | `false` |

```php
use Ameax\FilterCore\MatchModes\MatchMode;

$filter = new IsActiveFilter();

$filter->sanitizeValue('true', MatchMode::is());   // true
$filter->sanitizeValue('1', MatchMode::is());      // true
$filter->sanitizeValue('yes', MatchMode::is());    // true
$filter->sanitizeValue('on', MatchMode::is());     // true

$filter->sanitizeValue('false', MatchMode::is());  // false
$filter->sanitizeValue('0', MatchMode::is());      // false
$filter->sanitizeValue('no', MatchMode::is());     // false
$filter->sanitizeValue('off', MatchMode::is());    // false
```

### IntegerFilter Sanitization

Converts string numbers to integers, arrays to `BetweenValue`:

```php
$filter = new CountFilter();

// String to integer
$filter->sanitizeValue('123', MatchMode::is());     // 123
$filter->sanitizeValue('42', MatchMode::gt());      // 42

// Array to BetweenValue for BETWEEN mode
$filter->sanitizeValue(['min' => 10, 'max' => 100], MatchMode::between());
// Returns: BetweenValue(min: 10, max: 100)

$filter->sanitizeValue([10, 100], MatchMode::between());
// Returns: BetweenValue(min: 10, max: 100)
```

### TextFilter Sanitization

Trims whitespace:

```php
$filter = new NameFilter();

$filter->sanitizeValue('  john  ', MatchMode::contains());  // 'john'
$filter->sanitizeValue("\t test \n", MatchMode::is());      // 'test'
```

### SelectFilter Sanitization

Arrays remain arrays, strings remain strings:

```php
$filter = new StatusFilter();

$filter->sanitizeValue('active', MatchMode::is());           // 'active'
$filter->sanitizeValue(['a', 'b'], MatchMode::any());        // ['a', 'b']
```

## Validation

After sanitization, values are validated against filter-specific rules.

### Automatic Validation

Validation happens automatically when filters are applied:

```php
use Ameax\FilterCore\Exceptions\FilterValidationException;

try {
    User::query()
        ->applyFilter(FilterValue::for(StatusFilter::class)->is('invalid_status'))
        ->get();
} catch (FilterValidationException $e) {
    $e->getFilterKey();     // 'StatusFilter'
    $e->getErrors();        // ['value' => ['The selected value is invalid.']]
    $e->getFirstErrors();   // ['The selected value is invalid.']
}
```

### Validation Rules by Filter Type

#### BooleanFilter

Validates that the value is a boolean after sanitization:

```php
// Valid
FilterValue::for(IsActiveFilter::class)->is(true);
FilterValue::for(IsActiveFilter::class)->is('true');  // Sanitized to true

// Invalid - throws FilterValidationException
FilterValue::for(IsActiveFilter::class)->is('banana');
```

#### IntegerFilter

Validates that the value is numeric:

```php
// Valid
FilterValue::for(CountFilter::class)->is(42);
FilterValue::for(CountFilter::class)->is('42');       // Sanitized to 42
FilterValue::for(CountFilter::class)->between(10, 100);

// Invalid
FilterValue::for(CountFilter::class)->is('not_a_number');
```

#### SelectFilter

Validates that the value exists in the defined options:

```php
class StatusFilter extends SelectFilter
{
    public function options(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}

// Valid
FilterValue::for(StatusFilter::class)->is('active');
FilterValue::for(StatusFilter::class)->any(['active', 'inactive']);

// Invalid - 'pending' is not in options
FilterValue::for(StatusFilter::class)->is('pending');
```

#### TextFilter

Validates that the value is a string (minimal validation):

```php
// Valid
FilterValue::for(NameFilter::class)->contains('john');

// Most text values pass validation
```

## FilterValidationException

When validation fails, a `FilterValidationException` is thrown:

```php
use Ameax\FilterCore\Exceptions\FilterValidationException;

try {
    User::query()
        ->applyFilter(FilterValue::for(StatusFilter::class)->is('invalid'))
        ->get();
} catch (FilterValidationException $e) {
    // Filter that failed
    $filterKey = $e->getFilterKey();  // 'StatusFilter'

    // All validation errors (Laravel Validator format)
    $errors = $e->getErrors();
    // ['value' => ['The selected value is invalid.']]

    // First error for each field
    $firstErrors = $e->getFirstErrors();
    // ['The selected value is invalid.']

    // Original filter value that failed
    $filterValue = $e->getFilterValue();

    // Full exception message
    $message = $e->getMessage();
    // "Validation failed for filter 'StatusFilter': The selected value is invalid."
}
```

## Custom Validation Rules

Override `validationRules()` to add custom validation:

```php
use Ameax\FilterCore\Contracts\MatchModeContract;

class AgeFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'age';
    }

    public function validationRules(MatchModeContract $mode): array
    {
        return [
            'value' => 'required|integer|min:0|max:150',
        ];
    }
}
```

### Mode-Specific Validation

```php
class PriceFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'price';
    }

    public function validationRules(MatchModeContract $mode): array
    {
        $rules = ['value' => 'required|numeric|min:0'];

        if ($mode instanceof BetweenMatchMode) {
            $rules = [
                'value.min' => 'required|numeric|min:0',
                'value.max' => 'required|numeric|min:0|gte:value.min',
            ];
        }

        return $rules;
    }
}
```

## Type-Safe Values

For strict type checking without sanitization, use `typedValue()`:

```php
// BooleanFilter - expects bool
$filter = new IsActiveFilter();
$filter->typedValue(true);     // OK
$filter->typedValue('true');   // TypeError!

// IntegerFilter - expects int or BetweenValue
$filter = new CountFilter();
$filter->typedValue(42);                          // OK
$filter->typedValue(new BetweenValue(10, 100));   // OK
$filter->typedValue('42');                        // TypeError!

// TextFilter - expects string
$filter = new NameFilter();
$filter->typedValue('john');   // OK
$filter->typedValue(123);      // TypeError!

// SelectFilter - expects string or array
$filter = new StatusFilter();
$filter->typedValue('active');           // OK
$filter->typedValue(['a', 'b']);         // OK
```

## BetweenValue DTO

For range values, use the type-safe `BetweenValue` DTO:

```php
use Ameax\FilterCore\Data\BetweenValue;

// Create directly
$range = new BetweenValue(min: 10, max: 100);

// Create from array
$range = BetweenValue::fromArray(['min' => 10, 'max' => 100]);
$range = BetweenValue::fromArray([10, 100]);  // Indexed array also works

// Access values
$range->min;  // 10
$range->max;  // 100

// Convert to array
$range->toArray();  // ['min' => 10, 'max' => 100]

// Use in filter
FilterValue::for(CountFilter::class)->between(10, 100);
// Internally creates BetweenValue
```

## Disabling Validation

In rare cases, you may want to skip validation:

```php
use Ameax\FilterCore\Query\QueryApplicator;

$applicator = QueryApplicator::for(User::query())
    ->withFilters([StatusFilter::class])
    ->withValidation(false)  // Disable validation
    ->applyFilter($filterValue);
```

**Warning**: Disabling validation may lead to SQL errors or unexpected behavior.

## Validation in API Responses

Handle validation errors gracefully in APIs:

```php
use Ameax\FilterCore\Exceptions\FilterValidationException;

public function filter(Request $request)
{
    try {
        $selection = FilterSelection::fromArray($request->input());

        return User::query()
            ->applySelection($selection)
            ->paginate();

    } catch (FilterValidationException $e) {
        return response()->json([
            'error' => 'Invalid filter',
            'filter' => $e->getFilterKey(),
            'messages' => $e->getFirstErrors(),
        ], 422);
    }
}
```

## Next Steps

- [Advanced Usage](./09-advanced-usage.md) - Custom filter logic and extensibility
- [Filter Types](./02-filter-types.md) - Filter type definitions
