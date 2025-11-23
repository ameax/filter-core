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
│  │ - Select     │  │ - IS         │  │ - AND/OR Logic       │   │
│  │ - MultiSelect│  │ - IS_NOT     │  │ - Nested Groups      │   │
│  │ - Number     │  │ - ANY        │  │ - Persistence        │   │
│  │ - Date       │  │ - ALL        │  │                      │   │
│  │ - Text       │  │ - NONE       │  │                      │   │
│  │ - Boolean    │  │ - BETWEEN    │  │                      │   │
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
packages/filter-core/
├── src/
│   ├── Contracts/
│   │   ├── FilterContract.php
│   │   ├── FilterTypeContract.php
│   │   ├── MatchModeContract.php
│   │   ├── SelectionContract.php
│   │   ├── FilterGroupContract.php
│   │   └── QueryApplicatorContract.php
│   │
│   ├── Filters/
│   │   ├── AbstractFilter.php
│   │   ├── SelectFilter.php
│   │   ├── MultiSelectFilter.php
│   │   ├── NumberFilter.php
│   │   ├── DateFilter.php
│   │   ├── DateRangeFilter.php
│   │   ├── TextFilter.php
│   │   └── BooleanFilter.php
│   │
│   ├── MatchModes/
│   │   ├── AbstractMatchMode.php
│   │   ├── IsMatchMode.php
│   │   ├── IsNotMatchMode.php
│   │   ├── AnyMatchMode.php
│   │   ├── AllMatchMode.php
│   │   ├── NoneMatchMode.php
│   │   ├── BetweenMatchMode.php
│   │   ├── GreaterThanMatchMode.php
│   │   ├── LessThanMatchMode.php
│   │   ├── ContainsMatchMode.php
│   │   └── EmptyMatchMode.php
│   │
│   ├── Selections/
│   │   ├── Selection.php
│   │   ├── FilterGroup.php
│   │   ├── SelectionRepository.php
│   │   └── SelectionSerializer.php
│   │
│   ├── Query/
│   │   ├── QueryApplicator.php
│   │   ├── EloquentApplicator.php
│   │   └── CollectionApplicator.php
│   │
│   ├── Data/
│   │   ├── FilterDefinition.php
│   │   ├── FilterValue.php
│   │   ├── FilterState.php
│   │   └── SelectionData.php
│   │
│   └── Enums/
│       ├── FilterTypeEnum.php
│       ├── MatchModeEnum.php
│       ├── GroupOperatorEnum.php
│       └── ValueTypeEnum.php
│
├── config/
│   └── filter.php
│
├── database/
│   └── migrations/
│       └── create_selections_table.php
│
└── tests/
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

### 4. Query Applicator

Der Applicator wendet Filter auf verschiedene Datenquellen an:

```php
// Auf Eloquent Query
$users = QueryApplicator::apply(User::query(), $selection);

// Auf Collection
$filtered = QueryApplicator::applyToCollection($collection, $selection);
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
