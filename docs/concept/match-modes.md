# Match-Modi

## Übersicht

Match-Modi definieren **WIE** ein Filter-Wert mit den Daten verglichen wird. Jeder Match-Modus ist eine eigene Klasse, die `MatchModeContract` implementiert.

## Alle 17 MatchModes

### Gleichheit

| Key | Klasse | SQL | Beschreibung |
|-----|--------|-----|--------------|
| `is` | `IsMatchMode` | `= value` / `IN (...)` | Exakte Übereinstimmung |
| `isNot` | `IsNotMatchMode` | `!= value` / `NOT IN (...)` | Keine Übereinstimmung |

### Multi-Value

| Key | Klasse | SQL | Beschreibung |
|-----|--------|-----|--------------|
| `any` | `AnyMatchMode` | `IN (...)` | Mindestens ein Wert passt |
| `all` | `AllMatchMode` | Mehrere Bedingungen | Alle Werte müssen passen |
| `none` | `NoneMatchMode` | `NOT IN (...)` | Keiner der Werte darf passen |

### Zahlenvergleich

| Key | Klasse | SQL | Beschreibung |
|-----|--------|-----|--------------|
| `gt` | `GreaterThanMatchMode` | `> value` | Größer als |
| `gte` | `GreaterThanOrEqualMatchMode` | `>= value` | Größer oder gleich |
| `lt` | `LessThanMatchMode` | `< value` | Kleiner als |
| `lte` | `LessThanOrEqualMatchMode` | `<= value` | Kleiner oder gleich |
| `between` | `BetweenMatchMode` | `BETWEEN min AND max` | Zwischen zwei Werten |

### Textsuche

| Key | Klasse | SQL | Beschreibung |
|-----|--------|-----|--------------|
| `contains` | `ContainsMatchMode` | `LIKE '%value%'` | Enthält Text |
| `startsWith` | `StartsWithMatchMode` | `LIKE 'value%'` | Beginnt mit |
| `endsWith` | `EndsWithMatchMode` | `LIKE '%value'` | Endet mit |
| `regex` | `RegexMatchMode` | `REGEXP pattern` | Regular Expression (MySQL) |

### Null-Handling

| Key | Klasse | SQL | Beschreibung |
|-----|--------|-----|--------------|
| `empty` | `EmptyMatchMode` | `IS NULL OR = ''` | Ist leer/null |
| `notEmpty` | `NotEmptyMatchMode` | `IS NOT NULL AND != ''` | Ist nicht leer |

---

## Verwendung

### Fluent API (empfohlen)

```php
use Ameax\FilterCore\Selections\FilterSelection;
use App\Filters\StatusFilter;
use App\Filters\NameFilter;
use App\Filters\CountFilter;

$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->any(['active', 'pending'])
    ->where(CountFilter::class)->gt(10)
    ->where(CountFilter::class)->between(5, 100)
    ->where(NameFilter::class)->contains('search')
    ->where(NameFilter::class)->startsWith('A')
    ->where(NameFilter::class)->regex('^[A-Z][a-z]+$');
```

### Mit MatchMode Factory

```php
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\Data\FilterValue;

// Factory-Methoden
$isMode = MatchMode::is();
$gtMode = MatchMode::gt();
$regexMode = MatchMode::regex();

// In FilterValue
$filterValue = new FilterValue('StatusFilter', MatchMode::is(), 'active');
```

### Direkte Instanziierung

```php
use Ameax\FilterCore\MatchModes\IsMatchMode;
use Ameax\FilterCore\MatchModes\GreaterThanMatchMode;

$isMode = new IsMatchMode();
$gtMode = new GreaterThanMatchMode();
```

---

## Standard-Modi pro Filter-Typ

### SelectFilter
- `is`, `isNot`, `any`, `all`, `none`
- Default: `is`

### IntegerFilter
- `is`, `isNot`, `gt`, `gte`, `lt`, `lte`, `between`
- Default: `is`

### TextFilter
- `contains`, `startsWith`, `endsWith`, `regex`, `is`, `isNot`
- Default: `contains`

### BooleanFilter
- `is`
- Default: `is`

---

## Eigene MatchModes erstellen

```php
<?php

namespace App\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FuzzyMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'fuzzy';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        // Eigene Logik, z.B. SOUNDEX
        $query->whereRaw("SOUNDEX($column) = SOUNDEX(?)", [$value]);
    }
}
```

### Registrieren

```php
use Ameax\FilterCore\MatchModes\MatchMode;
use App\MatchModes\FuzzyMatchMode;

// Einmalig registrieren (z.B. in ServiceProvider)
MatchMode::register('fuzzy', FuzzyMatchMode::class);

// Verwenden
$mode = MatchMode::fuzzy();
```

---

## MatchMode in Filter einschränken

```php
class StrictStatusFilter extends SelectFilter
{
    public function allowedModes(): array
    {
        return [
            new IsMatchMode(),
            new IsNotMatchMode(),
            // Nur IS und IS_NOT erlaubt
        ];
    }
}
```

---

## JSON-Serialisierung

MatchModes werden über ihren `key()` serialisiert:

```json
{
    "filter": "StatusFilter",
    "mode": "is",
    "value": "active"
}
```

Beim Laden wird der key über `MatchMode::get('is')` aufgelöst.

---

## Aliases für JSON-Kompatibilität

Einige Modi haben snake_case Aliases:

| Primär | Alias |
|--------|-------|
| `isNot` | `is_not` |
| `notEmpty` | `not_empty` |
| `startsWith` | `starts_with` |
| `endsWith` | `ends_with` |
