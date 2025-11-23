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
│   ├── FilterValue.php          ✓ Implementiert
│   ├── FilterValueBuilder.php   ✓ Implementiert (Fluent API)
│   └── BetweenValue.php         ✓ Implementiert (Type-safe DTO für Ranges)
│
├── Enums/
│   ├── FilterTypeEnum.php       ✓ Implementiert (SELECT, INTEGER, TEXT, BOOLEAN)
│   ├── MatchModeEnum.php        ✓ Implementiert (IS, IS_NOT, ANY, NONE, GT, LT, BETWEEN, CONTAINS, EMPTY, NOT_EMPTY)
│   └── GroupOperatorEnum.php    ✓ Implementiert (AND, OR)
│
├── Selections/
│   └── FilterSelection.php      ✓ Implementiert (Gruppierung, JSON-Serialisierung)
│
├── Query/
│   └── QueryApplicator.php      ✓ Implementiert (Eloquent Builder, Sanitization, Validation)
│
├── Concerns/
│   └── Filterable.php           ✓ Implementiert (Model Trait)
│
├── Exceptions/
│   └── FilterValidationException.php  ✓ Implementiert
│
└── Filters/
    ├── Filter.php               ✓ Implementiert (Basisklasse mit apply, sanitizeValue, validationRules, typedValue)
    ├── SelectFilter.php         ✓ Implementiert
    ├── IntegerFilter.php        ✓ Implementiert
    ├── TextFilter.php           ✓ Implementiert
    ├── BooleanFilter.php        ✓ Implementiert
    ├── HasOptions.php           ✓ Implementiert (Interface)
    └── Dynamic/
        ├── DynamicFilter.php    ✓ Implementiert
        ├── DynamicSelectFilter.php   ✓ Implementiert
        ├── DynamicIntegerFilter.php  ✓ Implementiert
        ├── DynamicTextFilter.php     ✓ Implementiert
        └── DynamicBooleanFilter.php  ✓ Implementiert

tests/
├── TutorialTest.php             ✓ 40 Tests als vollständiges Tutorial
├── Query/
│   └── QueryApplicatorTest.php  ✓ 21 Tests für alle Match-Modi
├── Filters/
│   ├── FilterClassTest.php      ✓ Tests für Filter-Klassen
│   └── DynamicFilterTest.php    ✓ Tests für dynamische Filter
└── Selections/
    └── FilterSelectionTest.php  ✓ Tests für Selections
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

## Value Processing Pipeline

Der QueryApplicator verarbeitet Werte in einer definierten Pipeline:

```
Input → sanitizeValue() → typedValue() → validationRules() → apply()
```

### 1. Sanitization (sanitizeValue)

Automatische Konvertierung von Input-Werten:

```php
// In Filter.php
public function sanitizeValue(mixed $value, MatchModeEnum $mode): mixed
{
    return $value; // Default: keine Transformation
}

// BooleanFilter: "true", "1", "yes" → true
// IntegerFilter: "123" → 123, array → BetweenValue
// TextFilter: trim($value)
```

### 2. Type Checking (typedValue)

Strikte Typisierung mit PHP strict_types:

```php
// BooleanFilter
public function typedValue(bool $value): bool

// IntegerFilter
public function typedValue(int|BetweenValue $value): int|BetweenValue

// TextFilter
public function typedValue(string $value): string

// SelectFilter
public function typedValue(string|array $value): string|array
```

Bei TypeError wird `FilterValidationException` geworfen.

### 3. Validation (validationRules)

Laravel Validation Rules:

```php
// In Filter.php
public function validationRules(MatchModeEnum $mode): array
{
    return []; // Default: keine Validierung
}

// IntegerFilter
public function validationRules(MatchModeEnum $mode): array
{
    return ['value' => 'required|numeric'];
}

// SelectFilter mit Options
public function validationRules(MatchModeEnum $mode): array
{
    return ['value' => Rule::in(array_keys($this->options()))];
}
```

### 4. Custom Apply (apply)

Eigene Query-Logik pro Filter:

```php
public function apply(Builder|QueryBuilder $query, MatchModeEnum $mode, mixed $value): bool
{
    // Return true: Custom-Logik wurde angewendet
    // Return false: Standard QueryApplicator-Logik verwenden
    return false;
}
```

## BetweenValue DTO

Type-safe Repräsentation für BETWEEN-Werte:

```php
use Ameax\FilterCore\Data\BetweenValue;

// Erstellen
$between = new BetweenValue(min: 10, max: 100);
$between = BetweenValue::fromArray(['min' => 10, 'max' => 100]);
$between = BetweenValue::fromArray([10, 100]); // Indexed

// Zugriff
$between->min;  // 10
$between->max;  // 100

// Konvertierung
$between->toArray();  // ['min' => 10, 'max' => 100]
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

## Implementierungsstatus

### Phase 1 (Abgeschlossen)
- [x] Core-Package Implementierung
- [x] Filter-Typen (Select, Integer, Text, Boolean)
- [x] Match-Modi (IS, IS_NOT, ANY, NONE, GT, LT, BETWEEN, CONTAINS, EMPTY)
- [x] QueryApplicator mit Eloquent Integration
- [x] FilterSelection für Persistierung
- [x] Dynamic Filters
- [x] Filterable Trait
- [x] Value Sanitization Pipeline
- [x] Value Validation mit Laravel Validator
- [x] Type-Safe Values (typedValue)
- [x] Custom Filter Logic (apply)
- [x] BetweenValue DTO

### Phase 2 (Geplant)
- [ ] Livewire/Flux UI-Package
- [ ] Filter-Gruppen mit OR-Logik
- [ ] Erweiterbares Match-Mode System

### Phase 3 (Geplant)
- [ ] Filament-Integration
- [ ] Zusätzliche Filter-Typen (Date, DateTime, Decimal)

### Phase 4 (Geplant)
- [ ] Migration bestehender Filter
