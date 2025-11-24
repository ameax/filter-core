# Filter-Typen

## Übersicht

Filter-Typen definieren die Art der Daten und bestimmen welche Match-Modi verfügbar sind.

| Typ | Klasse | Erlaubte Modi | Default |
|-----|--------|---------------|---------|
| SELECT | `SelectFilter` | is, isNot, any, all, none | is |
| INTEGER | `IntegerFilter` | is, isNot, gt, gte, lt, lte, between | is |
| TEXT | `TextFilter` | contains, startsWith, endsWith, regex, is, isNot | contains |
| BOOLEAN | `BooleanFilter` | is | is |

---

## SelectFilter

Für Auswahl aus vordefinierten Optionen.

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\SelectFilter;

class StatusFilter extends SelectFilter
{
    public function column(): string
    {
        return 'status';
    }

    public function options(): array
    {
        return [
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'pending' => 'Ausstehend',
        ];
    }
}
```

### Verwendung

```php
// Einzelwert
->where(StatusFilter::class)->is('active')

// Mehrere Werte (OR)
->where(StatusFilter::class)->any(['active', 'pending'])

// Ausschluss
->where(StatusFilter::class)->none(['deleted', 'archived'])
```

---

## IntegerFilter

Für Ganzzahlen mit Vergleichsoperatoren.

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\IntegerFilter;

class CountFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'count';
    }
}
```

### Verwendung

```php
// Exakt
->where(CountFilter::class)->is(100)

// Vergleiche
->where(CountFilter::class)->gt(10)      // > 10
->where(CountFilter::class)->gte(10)     // >= 10
->where(CountFilter::class)->lt(100)     // < 100
->where(CountFilter::class)->lte(100)    // <= 100

// Bereich
->where(CountFilter::class)->between(10, 100)
```

### BetweenValue DTO

```php
use Ameax\FilterCore\Data\BetweenValue;

// Automatische Konvertierung
$filter->sanitizeValue(['min' => 10, 'max' => 100], MatchMode::between());
// → BetweenValue(min: 10, max: 100)

// Direkte Erstellung
$between = new BetweenValue(min: 10, max: 100);
$between = BetweenValue::fromArray([10, 100]);
```

---

## TextFilter

Für Textsuche mit verschiedenen Matching-Strategien.

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\TextFilter;

class NameFilter extends TextFilter
{
    public function column(): string
    {
        return 'name';
    }
}
```

### Verwendung

```php
// Enthält (Default)
->where(NameFilter::class)->contains('search')     // LIKE '%search%'

// Beginnt mit
->where(NameFilter::class)->startsWith('A')        // LIKE 'A%'

// Endet mit
->where(NameFilter::class)->endsWith('son')        // LIKE '%son'

// Regular Expression (MySQL)
->where(NameFilter::class)->regex('^[A-Z][a-z]+$')  // REGEXP

// Exakt
->where(NameFilter::class)->is('John')
```

---

## BooleanFilter

Für Ja/Nein-Werte.

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\BooleanFilter;

class IsActiveFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'is_active';
    }
}
```

### Verwendung

```php
->where(IsActiveFilter::class)->is(true)
->where(IsActiveFilter::class)->is(false)
```

### Automatische Konvertierung

```php
// Diese Werte werden zu `true`:
'true', '1', 'yes', 'on', 1, true

// Diese Werte werden zu `false`:
'false', '0', 'no', 'off', 0, false
```

---

## Dynamische Filter

Filter ohne eigene Klasse für einfache Anwendungsfälle.

```php
use Ameax\FilterCore\Filters\SelectFilter;
use Ameax\FilterCore\Filters\IntegerFilter;
use Ameax\FilterCore\Filters\TextFilter;

// Dynamischer SelectFilter
$statusFilter = SelectFilter::dynamic('status')
    ->column('status')
    ->options(['active' => 'Aktiv', 'inactive' => 'Inaktiv']);

// Dynamischer IntegerFilter
$countFilter = IntegerFilter::dynamic('count')
    ->column('item_count');

// Dynamischer TextFilter
$nameFilter = TextFilter::dynamic('name')
    ->column('full_name');

// Im Model verwenden
protected static function filterResolver(): \Closure
{
    return fn () => [
        StatusFilter::class,
        IntegerFilter::dynamic('count')->column('item_count'),
    ];
}
```

---

## Nullable Filter

Filter die auch NULL-Werte behandeln können.

```php
class OptionalFieldFilter extends SelectFilter
{
    public function column(): string
    {
        return 'optional_field';
    }

    public function nullable(): bool
    {
        return true;
    }
}
```

### Verwendung

```php
// Leere Werte finden
->where(OptionalFieldFilter::class)->empty()

// Nicht-leere Werte finden
->where(OptionalFieldFilter::class)->notEmpty()
```

---

## Filter mit Relation

Filter die über eine Relation angewendet werden.

```php
// Im Model
protected static function filterResolver(): \Closure
{
    return fn () => [
        // whereHas('department', fn($q) => $q->where('name', ...))
        DepartmentNameFilter::via('department'),

        // whereDoesntHave('department', ...)
        DepartmentNameFilter::viaDoesntHave('department'),

        // whereDoesntHave('department')
        SomeFilter::withoutRelation('department'),
    ];
}
```

---

## Eigene Filter-Typen

```php
<?php

namespace App\Filters;

use Ameax\FilterCore\Filters\Filter;
use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\BetweenMatchMode;
use Illuminate\Database\Eloquent\Builder;

abstract class DateFilter extends Filter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::INTEGER; // oder eigener Enum
    }

    public function defaultMode(): MatchModeContract
    {
        return new IsMatchMode();
    }

    public function allowedModes(): array
    {
        return [
            new IsMatchMode(),
            new BetweenMatchMode(),
            // ...
        ];
    }

    public function sanitizeValue(mixed $value, MatchModeContract $mode): mixed
    {
        // String zu Carbon konvertieren
        if (is_string($value)) {
            return \Carbon\Carbon::parse($value);
        }
        return $value;
    }
}
```

---

## Geplante Filter-Typen

- `DECIMAL` - Dezimalzahlen
- `DATE` - Datum
- `DATETIME` - Datum & Uhrzeit
- `MULTI_SELECT` - Mehrfachwert-Spalten (JSON/CSV)
