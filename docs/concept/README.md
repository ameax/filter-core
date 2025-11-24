# Filter Package Konzept

## Übersicht

Modulares, wiederverwendbares Filter-System für Laravel. Trennt Filter-Logik von UI-Darstellung und ermöglicht Verwendung in verschiedenen Kontexten (Blade, Livewire, Filament).

## Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                     UI Layer (separate Packages)                 │
├─────────────────┬─────────────────┬─────────────────────────────┤
│  filter-blade   │ filter-livewire │     filter-filament         │
└────────┬────────┴────────┬────────┴──────────────┬──────────────┘
         │                 │                        │
         ▼                 ▼                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                      filter-core                                 │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │ Filter Types │  │ Match Modes  │  │ Selection Groups     │   │
│  │              │  │              │  │                      │   │
│  │ - Select     │  │ - is, isNot  │  │ - AND/OR Logic       │   │
│  │ - Integer    │  │ - any, all   │  │ - Nested Groups      │   │
│  │ - Text       │  │ - none       │  │ - JSON Persistence   │   │
│  │ - Boolean    │  │ - gt,gte,lt  │  │                      │   │
│  │              │  │   lte,between│  │                      │   │
│  │              │  │ - contains   │  │                      │   │
│  │              │  │ - startsWith │  │                      │   │
│  │              │  │ - endsWith   │  │                      │   │
│  │              │  │ - regex      │  │                      │   │
│  │              │  │ - empty      │  │                      │   │
│  │              │  │ - notEmpty   │  │                      │   │
│  └──────────────┘  └──────────────┘  └──────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Query Applicator                       │   │
│  │                                                           │   │
│  │  - Eloquent Builder Integration  ✅                       │   │
│  │  - Collection Filtering  ✅                               │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Package-Struktur

```
src/
├── Concerns/
│   └── Filterable.php           # Model Trait
├── Contracts/
│   └── MatchModeContract.php    # Interface für MatchModes
├── Data/
│   ├── FilterDefinition.php     # Für dynamische Filter
│   ├── FilterValue.php          # Filter + Mode + Value
│   ├── FilterValueBuilder.php   # Fluent API
│   └── BetweenValue.php         # DTO für Ranges
├── Enums/
│   ├── FilterTypeEnum.php       # SELECT, INTEGER, TEXT, BOOLEAN
│   ├── GroupOperatorEnum.php    # AND, OR
│   └── RelationModeEnum.php     # HAS, DOESNT_HAVE, HAS_NONE
├── Filters/
│   ├── Filter.php               # Abstrakte Basisklasse
│   ├── SelectFilter.php
│   ├── IntegerFilter.php
│   ├── TextFilter.php
│   ├── BooleanFilter.php
│   └── Dynamic/                 # Dynamische Filter ohne eigene Klasse
├── MatchModes/                  # 17 MatchMode-Klassen
│   ├── MatchMode.php            # Factory
│   ├── IsMatchMode.php
│   ├── IsNotMatchMode.php
│   ├── AnyMatchMode.php
│   ├── AllMatchMode.php
│   ├── NoneMatchMode.php
│   ├── GreaterThanMatchMode.php
│   ├── GreaterThanOrEqualMatchMode.php
│   ├── LessThanMatchMode.php
│   ├── LessThanOrEqualMatchMode.php
│   ├── BetweenMatchMode.php
│   ├── ContainsMatchMode.php
│   ├── StartsWithMatchMode.php
│   ├── EndsWithMatchMode.php
│   ├── RegexMatchMode.php
│   ├── EmptyMatchMode.php
│   └── NotEmptyMatchMode.php
├── Collection/
│   └── CollectionApplicator.php
├── Query/
│   └── QueryApplicator.php
└── Selections/
    ├── FilterSelection.php      # Hauptklasse
    ├── FilterGroup.php          # AND/OR Gruppen
    └── FilterGroupBuilder.php   # Fluent API

tests/  → 263 Tests
```

## Kern-Konzepte

### 1. Filter-Klasse definieren

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

### 2. Model mit Filterable Trait

```php
<?php

namespace App\Models;

use Ameax\FilterCore\Concerns\Filterable;
use App\Filters\StatusFilter;
use App\Filters\CountFilter;

class User extends Model
{
    use Filterable;

    protected static function filterResolver(): \Closure
    {
        return fn () => [
            StatusFilter::class,
            CountFilter::class,
        ];
    }
}
```

### 3. Filter anwenden

```php
use Ameax\FilterCore\Selections\FilterSelection;
use App\Filters\StatusFilter;
use App\Filters\CountFilter;

// Einfache Filterung
$users = User::query()
    ->applyFilter(FilterValue::for(StatusFilter::class)->is('active'))
    ->get();

// Mit Selection (mehrere Filter)
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

$users = User::query()->applySelection($selection)->get();

// Mit OR-Logik
$selection = FilterSelection::make()
    ->where(StatusFilter::class)->is('active')
    ->orWhere(function ($g) {
        $g->where(StatusFilter::class)->is('pending');
        $g->where(CountFilter::class)->gte(100);
    });
```

### 4. Collection Filtering

Filter in-memory Collections mit derselben Logik wie Query-Filtering:

```php
// Collection laden
$collection = User::all();

// Einfache Filterung
$filtered = User::filterCollection($collection, [
    FilterValue::for(StatusFilter::class)->is('active'),
]);

// Mit Selection (inkl. OR-Logik)
$selection = FilterSelection::makeOr()
    ->where(StatusFilter::class)->is('active')
    ->where(StatusFilter::class)->is('pending');

$filtered = User::filterCollectionWithSelection($collection, $selection);

// Direkt mit CollectionApplicator
use Ameax\FilterCore\Collection\CollectionApplicator;

$filtered = CollectionApplicator::for($collection)
    ->withFilters([StatusFilter::class, CountFilter::class])
    ->applyFilters([
        FilterValue::for(StatusFilter::class)->is('active'),
        FilterValue::for(CountFilter::class)->gte(10),
    ])
    ->getCollection();
```

## Alle MatchModes

| Key | Beschreibung | Beispiel |
|-----|-------------|----------|
| `is` | Gleichheit | `->is('active')` |
| `isNot` | Ungleichheit | `->isNot('deleted')` |
| `any` | Einer der Werte | `->any(['a', 'b'])` |
| `all` | Alle Werte | `->all(['a', 'b'])` |
| `none` | Keiner der Werte | `->none(['x', 'y'])` |
| `gt` | Größer als | `->gt(10)` |
| `gte` | Größer oder gleich | `->gte(10)` |
| `lt` | Kleiner als | `->lt(100)` |
| `lte` | Kleiner oder gleich | `->lte(100)` |
| `between` | Zwischen | `->between(10, 100)` |
| `contains` | Enthält Text | `->contains('search')` |
| `startsWith` | Beginnt mit | `->startsWith('pre')` |
| `endsWith` | Endet mit | `->endsWith('fix')` |
| `regex` | Regular Expression | `->regex('^[A-Z].*')` |
| `empty` | Ist leer/null | `->empty()` |
| `notEmpty` | Ist nicht leer | `->notEmpty()` |

## Relation Filter

```php
// Filter über Relation (whereHas)
DepartmentNameFilter::via('department')

// Negiert (whereDoesntHave)
DepartmentNameFilter::viaDoesntHave('department')

// Ohne Relation
SomeFilter::withoutRelation('department')
```

## JSON-Serialisierung

```php
// Speichern
$json = $selection->toJson();

// Laden
$selection = FilterSelection::fromJson($json);

// Format
{
    "name": "Meine Filter",
    "groups": [
        {
            "operator": "and",
            "filters": [
                {"filter": "StatusFilter", "mode": "is", "value": "active"}
            ]
        }
    ]
}
```

## Weiterführende Dokumentation

- [Filter-Typen](./filter-types.md) - SELECT, INTEGER, TEXT, BOOLEAN
- [Match-Modi](./match-modes.md) - Alle 17 MatchModes im Detail
- [Query-Integration](./query-integration.md) - Filterable Trait & QueryApplicator
- [Selektionen](./selections.md) - Filter-Gruppen und OR-Logik
- [UI-Adapter](./ui-adapters.md) - Konzept für UI-Packages

## Implementierungsstatus

### Abgeschlossen ✅

- Filter-Typen: Select, Integer, Text, Boolean
- 17 MatchModes inkl. regex
- QueryApplicator mit Eloquent Integration
- CollectionApplicator für In-Memory Filterung
- FilterSelection mit OR-Logik
- Verschachtelte FilterGroups
- Dynamic Filters
- Filterable Model Trait
- Relation Filter (via, viaDoesntHave, withoutRelation)
- JSON-Serialisierung
- Value Sanitization & Validation
- 263 Tests

### Geplant

- Zusätzliche Filter-Typen (Date, DateTime, Decimal)
- UI-Packages (separate Repositories)
