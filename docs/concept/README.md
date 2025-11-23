# Filter Package Konzept

## Übersicht

Dieses Dokument beschreibt das Konzept für ein modulares, wiederverwendbares Filter-System für Laravel-Anwendungen. Das System trennt die Filter-Logik von der UI-Darstellung und ermöglicht die Verwendung in verschiedenen Kontexten (Blade, Livewire, Filament).

## Motivation

### Aktuelle Situation

Das bestehende Filter-System in `Support\Filters` bietet bereits:
- Filter-Definitionen mit `FilterAbstract`
- Conditions (`IS`, `IS_NOT`, `BETWEEN`, etc.)
- Input-Typen (`SELECT_SINGLE`, `SELECT_MULTIPLE`, `DATE_RANGE`, etc.)
- `FiltersService` für Query-Anwendung

### Limitationen des aktuellen Systems

1. **Enge UI-Kopplung**: Views sind direkt in `FilterInputTypeEnum` definiert
2. **Fehlende Match-Modi**: Kein `ANY`/`ALL`/`NONE` für Multi-Select
3. **Keine Filter-Gruppen**: Keine AND/OR Kombinationen
4. **Keine Persistierung**: Keine speicherbaren Selektionen
5. **Model-spezifisch**: Filter sind stark an spezifische Models gebunden

## Architektur-Übersicht

```
┌─────────────────────────────────────────────────────────────────┐
│                        UI Layer                                  │
├─────────────────┬─────────────────┬─────────────────────────────┤
│  filter-blade   │ filter-livewire │     filter-filament         │
│                 │    (Flux)       │                             │
└────────┬────────┴────────┬────────┴──────────────┬──────────────┘
         │                 │                        │
         ▼                 ▼                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                      filter-core                                 │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │ Filter Types │  │ Match Modes  │  │ Selection Groups     │   │
│  │              │  │              │  │                      │   │
│  │ Phase 1:     │  │ Phase 1:     │  │ - AND/OR Logic       │   │
│  │ - Select     │  │ - IS, IS_NOT │  │ - Nested Groups      │   │
│  │ - Integer    │  │ - ANY, NONE  │  │ - Persistence        │   │
│  │ - Text       │  │ - GT, LT     │  │                      │   │
│  │ - Boolean    │  │ - BETWEEN    │  │                      │   │
│  │              │  │ - CONTAINS   │  │                      │   │
│  │ Phase 2:     │  │ - EMPTY      │  │                      │   │
│  │ - MultiSelect│  │              │  │                      │   │
│  │ - Decimal    │  │ Phase 2:     │  │                      │   │
│  │ - Date       │  │ - ALL        │  │                      │   │
│  │ - DateTime   │  │ - GTE, LTE   │  │                      │   │
│  │              │  │ - STARTS/END │  │                      │   │
│  └──────────────┘  └──────────────┘  └──────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Query Applicator                       │   │
│  │                                                           │   │
│  │  - Eloquent Builder Integration                           │   │
│  │  - Collection Filtering                                   │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Package-Struktur

### filter-core (Hauptpaket)

```
src/
├── Data/
│   ├── FilterDefinition.php     ✓ Implementiert
│   └── FilterValue.php          ✓ Implementiert
│
├── Enums/
│   ├── FilterTypeEnum.php       ✓ Implementiert (Phase 1: SELECT, INTEGER, TEXT, BOOLEAN)
│   ├── MatchModeEnum.php        ✓ Implementiert (Phase 1: IS, IS_NOT, ANY, NONE, GT, LT, BETWEEN, CONTAINS, EMPTY, NOT_EMPTY)
│   └── GroupOperatorEnum.php    ✓ Implementiert (AND, OR)
│
├── Selections/                  □ Geplant
│   ├── Selection.php
│   └── FilterGroup.php
│
├── Query/
│   └── QueryApplicator.php      ✓ Implementiert (Eloquent Builder)
│
└── Filters/                     □ Geplant (Phase 1)
    ├── SelectFilter.php
    ├── IntegerFilter.php
    ├── TextFilter.php
    └── BooleanFilter.php

tests/
├── Models/
│   └── Koi.php                  ✓ Test-Model mit allen Phase 1 Typen
├── Query/
│   └── QueryApplicatorTest.php  ✓ 21 Tests für alle Match-Modi
└── database/migrations/
    └── create_koi_table.php     ✓ Test-Migration
```

## Kern-Konzepte

### 1. Filter Definition

Ein Filter definiert **WAS** gefiltert werden kann:

```php
$filter = SelectFilter::make('user_id')
    ->label('Benutzer')
    ->options(fn () => User::pluck('name', 'id'))
    ->allowedMatchModes([
        MatchModeEnum::IS,
        MatchModeEnum::IS_NOT,
        MatchModeEnum::EMPTY,
    ]);
```

### 2. Filter Value

Ein FilterValue enthält den **aktuellen Zustand** eines Filters:

```php
$filterValue = FilterValue::make()
    ->filter('user_id')
    ->matchMode(MatchModeEnum::IS)
    ->value([1, 2, 3]);
```

### 3. Selection (Filter-Gruppe)

Eine Selection kombiniert mehrere FilterValues mit Logik:

```php
$selection = Selection::make('Aktive Premium-Kunden')
    ->where('status', MatchModeEnum::IS, 'active')
    ->where('subscription', MatchModeEnum::ANY, ['premium', 'enterprise'])
    ->orWhere(function (FilterGroup $group) {
        $group->where('created_at', MatchModeEnum::GREATER_THAN, now()->subDays(30))
              ->where('orders_count', MatchModeEnum::GREATER_THAN, 0);
    });
```

### 4. Query Applicator ✓

Der Applicator wendet Filter auf Eloquent Queries an:

```php
use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\MatchModeEnum;

// QueryApplicator mit FilterDefinitions erstellen
$applicator = QueryApplicator::for(User::query())
    ->withDefinitions($filterDefinitions);

// Einzelnen Filter anwenden
$applicator->applyFilter(
    FilterValue::make('status', MatchModeEnum::IS, 'active')
);

// Mehrere Filter anwenden
$applicator->applyFilters([
    FilterValue::make('status', MatchModeEnum::IS, 'active'),
    FilterValue::make('count', MatchModeEnum::GREATER_THAN, 10),
]);

// Query abrufen und ausführen
$users = $applicator->getQuery()->get();
```

## Weiterführende Dokumentation

- [Filter-Typen](./filter-types.md) - Detaillierte Beschreibung aller Filter-Typen
- [Match-Modi](./match-modes.md) - Verfügbare Match-Modi und ihre Logik
- [Query-Integration](./query-integration.md) - Integration mit Query Buildern
- [Selektionen](./selections.md) - Filter-Gruppen und Persistierung
- [UI-Adapter](./ui-adapters.md) - Konzept für UI-Packages

## Migration vom bestehenden System

Das neue System soll das bestehende `Support\Filters` System nicht ersetzen, sondern als eigenständiges Package entwickelt werden. Eine spätere Migration ist möglich durch:

1. Adapter-Klassen für bestehende Filter
2. Parallelbetrieb während der Übergangsphase
3. Schrittweise Migration einzelner Domains

## Nächste Schritte

1. **Phase 1**: Core-Package Implementierung
2. **Phase 2**: Livewire/Flux UI-Package
3. **Phase 3**: Filament-Integration
4. **Phase 4**: Migration bestehender Filter
