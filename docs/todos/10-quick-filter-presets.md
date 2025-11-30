# Quick Filter Presets - Konzept

## Übersicht

Benutzer-definierbare Quick Date Range Presets. Ersetzt die hardcodierten QuickDateRange Enum-Werte durch konfigurierbare Datenbankeinträge. Labels werden automatisch aus der Config generiert.

## Schema: `filter_quick_presets`

| Spalte | Typ | Nullable | Beschreibung |
|--------|-----|----------|--------------|
| `id` | bigint | - | PK |
| `scope` | varchar(255) | ✓ | `null`=global, sonst frei definierbar |
| `date_range_config` | json | - | DateRangeValue Config |
| `direction` | varchar(10) | ✓ | `null`=beide, `past`, `future` |
| `sort_order` | int | - | Reihenfolge |
| `is_active` | boolean | - | Aktiv |
| `timestamps` | | | |

## Scope

```
scope = NULL           → Global (überall verfügbar)
scope = 'invoices'     → Nur für Rechnungs-Filter
scope = 'warranty'     → Nur für Garantie-Filter
scope = 'tenant_123'   → Nur für Mandant 123
```

## Direction

```
direction = NULL       → Beide (Today, This Week, This Month)
direction = 'past'     → Nur Past-Filter (Reporting, Analyse)
direction = 'future'   → Nur Future-Filter (Fristen, Garantie)
```

## Label-Generierung

Labels werden automatisch aus `date_range_config` generiert via `DateRangeValue::toLabel()`:

```php
// Quick
{"type":"quick","quick":"today"}
→ "Heute" / "Today"

{"type":"quick","quick":"this_week"}
→ "Diese Woche" / "This Week"

// Relative
{"type":"relative","amount":30,"unit":"day","direction":"past"}
→ "Letzte 30 Tage" / "Last 30 Days"

{"type":"relative","amount":2,"unit":"year","direction":"future"}
→ "Nächste 2 Jahre" / "Next 2 Years"

// Specific
{"type":"specific","unit":"quarter","quarter":1,"yearOffset":-2}
→ "Q1 vor 2 Jahren" / "Q1 2 Years Ago"

{"type":"specific","unit":"month","month":6,"yearOffset":-1}
→ "Juni letztes Jahr" / "June Last Year"

{"type":"specific","unit":"half_year","startMonth":1,"endMonth":6,"yearOffset":0}
→ "H1 dieses Jahr" / "H1 This Year"

// Annual Range
{"type":"annual_range","startMonth":7,"yearOffset":0}
→ "Geschäftsjahr (Jul-Jun)" / "Fiscal Year (Jul-Jun)"

// Custom
{"type":"custom","start":"2024-01-01","end":"2024-06-30"}
→ "01.01.2024 - 30.06.2024"

// Older/Newer Than
{"type":"relative","amount":90,"unit":"day","openStart":true}
→ "Älter als 90 Tage" / "Older than 90 Days"

{"type":"relative","amount":30,"unit":"day","endDate":"now"}
→ "Neuer als 30 Tage" / "Newer than 30 Days"
```

## API Design

### Model: QuickFilterPreset

```php
class QuickFilterPreset extends Model
{
    protected $table = 'filter_quick_presets';

    protected $casts = [
        'date_range_config' => 'array',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive(Builder $query): Builder;
    public function scopeGlobal(Builder $query): Builder;
    public function scopeForScope(Builder $query, string $scope): Builder;
    public function scopeForScopes(Builder $query, array $scopes): Builder;
    public function scopeForDirection(Builder $query, ?array $directions): Builder;
    public function scopeOrdered(Builder $query): Builder;

    // Accessors
    public function getDateRangeValueAttribute(): DateRangeValue;
    public function getLabelAttribute(): string; // Auto-generated

    // Methods
    public function resolve(?Carbon $reference = null): ResolvedDateRange;
}
```

### DateRangeValue::toLabel()

```php
class DateRangeValue
{
    /**
     * Generate human-readable label from config.
     */
    public function toLabel(?string $locale = null): string
    {
        return match ($this->type) {
            DateRangeType::QUICK => $this->quickLabel($locale),
            DateRangeType::RELATIVE => $this->relativeLabel($locale),
            DateRangeType::SPECIFIC => $this->specificLabel($locale),
            DateRangeType::ANNUAL_RANGE => $this->annualRangeLabel($locale),
            DateRangeType::CUSTOM => $this->customLabel($locale),
            DateRangeType::EXPRESSION => $this->expressionLabel($locale),
        };
    }
}
```

### Query Beispiele

```php
// Alle globalen aktiven Presets
QuickFilterPreset::active()->global()->ordered()->get();

// Globale + scope-spezifische, nur past
QuickFilterPreset::active()
    ->forScope('invoices')
    ->forDirection([DateDirection::PAST])
    ->ordered()
    ->get();
```

## Beispieldaten

```
id | scope     | direction | sort | date_range_config                          | → Label
---|-----------|-----------|------|--------------------------------------------|------------------
1  | NULL      | NULL      | 10   | {"type":"quick","quick":"today"}           | Heute
2  | NULL      | NULL      | 20   | {"type":"quick","quick":"this_week"}       | Diese Woche
3  | NULL      | NULL      | 30   | {"type":"quick","quick":"this_month"}      | Dieser Monat
4  | NULL      | past      | 100  | {"type":"relative","amount":30,...}        | Letzte 30 Tage
5  | NULL      | past      | 110  | {"type":"annual_range","startMonth":7,...} | Geschäftsjahr
6  | warranty  | future    | 10   | {"type":"relative","amount":2,"unit":"year"} | Nächste 2 Jahre
```

## Flow

1. **Frontend holt Presets:**
   ```php
   $presets = QuickFilterPreset::active()
       ->forScopes($filter->quickPresetScopes())
       ->forDirection($filter->allowedDirections())
       ->ordered()
       ->get();
   ```

2. **Response:**
   ```json
   [
     {"id": 1, "label": "Heute", "config": {"type":"quick","quick":"today"}},
     {"id": 2, "label": "Diese Woche", "config": {"type":"quick","quick":"this_week"}}
   ]
   ```

3. **User wählt Preset → Duale Speicherung:**
   ```json
   {
     "filter": "CreatedAtFilter",
     "mode": "dateRange",
     "filter_quick_preset_id": 42,
     "value": {"type":"relative","direction":"past","amount":30,"unit":"day"}
   }
   ```

## Duale Speicherung

Bei persistenter Speicherung (Dashboard-Widgets, gespeicherte Filter) werden **beide** Werte gespeichert:

| Feld | Zweck |
|------|-------|
| `filter_quick_preset_id` | UI-State: Welcher Preset war ausgewählt |
| `value` (date_range_config) | Tatsächlicher Filter-Wert |

**Vorteile:**

```
┌─────────────────────────────────────────────────────────────────┐
│ Szenario                    │ Verhalten                         │
├─────────────────────────────┼───────────────────────────────────┤
│ Preset existiert            │ UI zeigt Preset als selected      │
│ Preset wurde gelöscht       │ Filter funktioniert weiter,       │
│                             │ UI zeigt "Benutzerdefiniert"      │
│ Preset wurde geändert       │ Gespeicherter Filter bleibt       │
│                             │ unverändert (gewollt!)            │
└─────────────────────────────────────────────────────────────────┘
```

**UI-Logik beim Laden:**

```php
// Prüfen ob Preset noch existiert
$preset = $filterQuickPresetId
    ? QuickFilterPreset::find($filterQuickPresetId)
    : null;

if ($preset) {
    // Quick-Modus: Preset als selected anzeigen
    $uiMode = 'preset';
    $selectedPresetId = $preset->id;
} else {
    // Custom-Modus: Benutzerdefiniert anzeigen
    $uiMode = 'custom';
    $selectedPresetId = null;
}

// Filter IMMER mit gespeicherter Config ausführen
$dateRange = DateRangeValue::fromArray($value);
```

**Beispiel: Dashboard Widget Storage:**

```php
// In dashboard_widgets.filter_config (JSON)
{
    "filters": [
        {
            "key": "CreatedAtFilter",
            "mode": "dateRange",
            "filter_quick_preset_id": 42,
            "value": {
                "type": "relative",
                "direction": "past",
                "amount": 30,
                "unit": "day",
                "includePartial": true
            }
        }
    ]
}
```

## Status: Completed

Alle Schritte wurden implementiert:

1. [x] Migration erstellen (`create_filter_quick_presets_table.php.stub`)
2. [x] QuickFilterPreset Model implementieren (`src/Models/QuickFilterPreset.php`)
3. [x] DateRangeValue::toLabel() implementieren (`src/DateRange/DateRangeValue.php`)
4. [x] Seeder mit ~100 Presets (`database/seeders/QuickFilterPresetSeeder.php`)
5. [x] Translation files (EN/DE) (`resources/lang/*/date_range.php`)
6. [x] Tests schreiben (`tests/Models/QuickFilterPresetTest.php`, `tests/DateRange/DateRangeValueLabelTest.php`)
