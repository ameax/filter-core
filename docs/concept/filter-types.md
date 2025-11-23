# Filter-Typen

## Phase 1 Implementierung

**Aktive Typen:**
- `SELECT` - Einzelwert-Auswahl (Dropdown)
- `INTEGER` - Ganzzahlen
- `TEXT` - Freitext-Suche
- `BOOLEAN` - Ja/Nein

**Phase 2 (geplant):**
- `MULTI_SELECT` - Mehrfachwert-Spalten (JSON/CSV)
- `DECIMAL` - Dezimalzahlen
- `DATE` - Datum
- `DATETIME` - Datum & Uhrzeit

---

## Übersicht

Filter-Typen definieren die Art der Daten, die gefiltert werden sollen, und bestimmen welche Match-Modi verfügbar sind.

## Basis-Klasse: AbstractFilter

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Contracts\FilterContract;
use Ameax\Filter\Data\FilterDefinition;
use Ameax\Filter\Enums\MatchModeEnum;

abstract class AbstractFilter implements FilterContract
{
    protected string $key;
    protected string $label;
    protected string $column;
    protected ?string $relation = null;
    protected array $allowedMatchModes = [];
    protected MatchModeEnum $defaultMatchMode;
    protected mixed $defaultValue = null;
    protected bool $nullable = true;
    protected array $meta = [];

    public static function make(string $key): static
    {
        $filter = new static();
        $filter->key = $key;
        $filter->column = $key;

        return $filter;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function column(string $column): static
    {
        $this->column = $column;
        return $this;
    }

    public function relation(string $relation): static
    {
        $this->relation = $relation;
        return $this;
    }

    public function allowedMatchModes(array $modes): static
    {
        $this->allowedMatchModes = $modes;
        return $this;
    }

    public function defaultMatchMode(MatchModeEnum $mode): static
    {
        $this->defaultMatchMode = $mode;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    abstract public function type(): FilterTypeEnum;

    abstract public function getDefaultMatchModes(): array;

    public function toDefinition(): FilterDefinition
    {
        return new FilterDefinition(
            key: $this->key,
            type: $this->type(),
            label: $this->label ?? $this->key,
            column: $this->column,
            relation: $this->relation,
            allowedMatchModes: $this->allowedMatchModes ?: $this->getDefaultMatchModes(),
            defaultMatchMode: $this->defaultMatchMode ?? $this->getDefaultMatchModes()[0],
            defaultValue: $this->defaultValue,
            nullable: $this->nullable,
            meta: $this->meta,
        );
    }
}
```

---

## SelectFilter (Single Select)

Für Auswahl eines einzelnen Wertes aus einer Liste.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class SelectFilter extends AbstractFilter
{
    protected array|\Closure $options = [];
    protected ?string $optionLabelKey = null;
    protected ?string $optionValueKey = null;
    protected bool $searchable = false;
    protected ?string $searchUrl = null;

    public function options(array|\Closure $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function optionLabel(string $key): static
    {
        $this->optionLabelKey = $key;
        return $this;
    }

    public function optionValue(string $key): static
    {
        $this->optionValueKey = $key;
        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function searchUrl(string $url): static
    {
        $this->searchUrl = $url;
        $this->searchable = true;
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::SELECT;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
            MatchModeEnum::EMPTY,
        ];
    }

    public function getOptions(): array
    {
        $options = is_callable($this->options)
            ? ($this->options)()
            : $this->options;

        return $options;
    }
}
```

### Verwendung

```php
// Mit statischen Optionen
SelectFilter::make('status')
    ->label('Status')
    ->options([
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'pending' => 'Ausstehend',
    ]);

// Mit dynamischen Optionen
SelectFilter::make('category_id')
    ->label('Kategorie')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->searchable();

// Mit Relation
SelectFilter::make('author_id')
    ->label('Autor')
    ->column('id')
    ->relation('author')
    ->options(fn () => User::authors()->pluck('name', 'id'));

// Mit Remote-Suche
SelectFilter::make('customer_id')
    ->label('Kunde')
    ->searchUrl(route('api.customers.search'))
    ->searchable();
```

---

## MultiSelectFilter

Für Auswahl mehrerer Werte mit verschiedenen Match-Logiken.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class MultiSelectFilter extends SelectFilter
{
    protected int $minSelected = 0;
    protected ?int $maxSelected = null;

    public function min(int $min): static
    {
        $this->minSelected = $min;
        return $this;
    }

    public function max(int $max): static
    {
        $this->maxSelected = $max;
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::MULTI_SELECT;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::ANY,      // Mindestens einer der Werte
            MatchModeEnum::ALL,      // Alle Werte müssen passen
            MatchModeEnum::NONE,     // Keiner der Werte
            MatchModeEnum::IS,       // Exakt diese Werte (für JSON-Spalten)
            MatchModeEnum::EMPTY,
        ];
    }
}
```

### Verwendung

```php
// Tags mit ANY-Logik (Standard)
MultiSelectFilter::make('tags')
    ->label('Tags')
    ->options(fn () => Tag::pluck('name', 'id'))
    ->defaultMatchMode(MatchModeEnum::ANY);

// Pflichtfelder mit ALL-Logik
MultiSelectFilter::make('required_skills')
    ->label('Erforderliche Fähigkeiten')
    ->options(fn () => Skill::pluck('name', 'id'))
    ->defaultMatchMode(MatchModeEnum::ALL)
    ->min(1);

// Ausschluss mit NONE-Logik
MultiSelectFilter::make('excluded_categories')
    ->label('Ausgeschlossene Kategorien')
    ->options(fn () => Category::pluck('name', 'id'))
    ->defaultMatchMode(MatchModeEnum::NONE);
```

---

## NumberFilter

Für numerische Werte mit Vergleichsoperatoren.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class NumberFilter extends AbstractFilter
{
    protected ?float $min = null;
    protected ?float $max = null;
    protected int $decimals = 0;
    protected ?string $unit = null;
    protected float $step = 1;

    public function min(float $min): static
    {
        $this->min = $min;
        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;
        return $this;
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;
        return $this;
    }

    public function unit(string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function step(float $step): static
    {
        $this->step = $step;
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::NUMBER;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
            MatchModeEnum::GREATER_THAN,
            MatchModeEnum::GREATER_THAN_OR_EQUAL,
            MatchModeEnum::LESS_THAN,
            MatchModeEnum::LESS_THAN_OR_EQUAL,
            MatchModeEnum::BETWEEN,
            MatchModeEnum::EMPTY,
        ];
    }
}
```

### Verwendung

```php
// Preis-Filter
NumberFilter::make('price')
    ->label('Preis')
    ->decimals(2)
    ->unit('€')
    ->min(0)
    ->defaultMatchMode(MatchModeEnum::BETWEEN);

// Menge-Filter
NumberFilter::make('quantity')
    ->label('Menge')
    ->min(0)
    ->step(1)
    ->defaultMatchMode(MatchModeEnum::GREATER_THAN_OR_EQUAL);

// Prozent-Filter
NumberFilter::make('discount_percent')
    ->label('Rabatt')
    ->min(0)
    ->max(100)
    ->unit('%')
    ->decimals(1);
```

---

## NumberRangeFilter

Für Bereichs-Eingaben mit Von-Bis-Werten.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class NumberRangeFilter extends NumberFilter
{
    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::NUMBER_RANGE;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::BETWEEN,
        ];
    }
}
```

### Verwendung

```php
NumberRangeFilter::make('price_range')
    ->label('Preisbereich')
    ->column('price')
    ->decimals(2)
    ->unit('€')
    ->min(0);
```

---

## DateFilter

Für Datumswerte.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class DateFilter extends AbstractFilter
{
    protected bool $withTime = false;
    protected ?string $timezone = null;
    protected ?string $displayFormat = null;
    protected ?\DateTimeInterface $minDate = null;
    protected ?\DateTimeInterface $maxDate = null;

    public function withTime(bool $withTime = true): static
    {
        $this->withTime = $withTime;
        return $this;
    }

    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function displayFormat(string $format): static
    {
        $this->displayFormat = $format;
        return $this;
    }

    public function minDate(\DateTimeInterface $date): static
    {
        $this->minDate = $date;
        return $this;
    }

    public function maxDate(\DateTimeInterface $date): static
    {
        $this->maxDate = $date;
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATE;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
            MatchModeEnum::GREATER_THAN,
            MatchModeEnum::GREATER_THAN_OR_EQUAL,
            MatchModeEnum::LESS_THAN,
            MatchModeEnum::LESS_THAN_OR_EQUAL,
            MatchModeEnum::BETWEEN,
            MatchModeEnum::EMPTY,
        ];
    }
}
```

### Verwendung

```php
// Einfacher Datumsfilter
DateFilter::make('created_at')
    ->label('Erstellt am')
    ->defaultMatchMode(MatchModeEnum::GREATER_THAN_OR_EQUAL);

// Mit Zeitzone
DateFilter::make('scheduled_at')
    ->label('Geplant für')
    ->withTime()
    ->timezone('Europe/Berlin');

// Mit Einschränkungen
DateFilter::make('birth_date')
    ->label('Geburtsdatum')
    ->maxDate(now())
    ->displayFormat('d.m.Y');
```

---

## DateRangeFilter

Für Datumsbereiche mit Von-Bis-Auswahl.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class DateRangeFilter extends DateFilter
{
    protected array $presets = [];

    public function presets(array $presets): static
    {
        $this->presets = $presets;
        return $this;
    }

    public function withDefaultPresets(): static
    {
        $this->presets = [
            'today' => ['label' => 'Heute', 'from' => now()->startOfDay(), 'to' => now()->endOfDay()],
            'yesterday' => ['label' => 'Gestern', 'from' => now()->subDay()->startOfDay(), 'to' => now()->subDay()->endOfDay()],
            'this_week' => ['label' => 'Diese Woche', 'from' => now()->startOfWeek(), 'to' => now()->endOfWeek()],
            'last_week' => ['label' => 'Letzte Woche', 'from' => now()->subWeek()->startOfWeek(), 'to' => now()->subWeek()->endOfWeek()],
            'this_month' => ['label' => 'Dieser Monat', 'from' => now()->startOfMonth(), 'to' => now()->endOfMonth()],
            'last_month' => ['label' => 'Letzter Monat', 'from' => now()->subMonth()->startOfMonth(), 'to' => now()->subMonth()->endOfMonth()],
            'this_quarter' => ['label' => 'Dieses Quartal', 'from' => now()->startOfQuarter(), 'to' => now()->endOfQuarter()],
            'this_year' => ['label' => 'Dieses Jahr', 'from' => now()->startOfYear(), 'to' => now()->endOfYear()],
            'last_30_days' => ['label' => 'Letzte 30 Tage', 'from' => now()->subDays(30), 'to' => now()],
            'last_90_days' => ['label' => 'Letzte 90 Tage', 'from' => now()->subDays(90), 'to' => now()],
        ];
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::DATE_RANGE;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::BETWEEN,
        ];
    }
}
```

### Verwendung

```php
DateRangeFilter::make('created_at')
    ->label('Erstellt')
    ->withDefaultPresets();

DateRangeFilter::make('period')
    ->label('Zeitraum')
    ->column('date')
    ->presets([
        'q1' => ['label' => 'Q1', 'from' => now()->startOfYear(), 'to' => now()->startOfYear()->addMonths(3)],
        'q2' => ['label' => 'Q2', 'from' => now()->startOfYear()->addMonths(3), 'to' => now()->startOfYear()->addMonths(6)],
        // ...
    ]);
```

---

## TextFilter

Für Textsuche und String-Matching.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class TextFilter extends AbstractFilter
{
    protected ?int $minLength = null;
    protected ?int $maxLength = null;
    protected ?string $placeholder = null;
    protected bool $caseSensitive = false;
    protected array $searchColumns = [];

    public function minLength(int $length): static
    {
        $this->minLength = $length;
        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;
        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function caseSensitive(bool $caseSensitive = true): static
    {
        $this->caseSensitive = $caseSensitive;
        return $this;
    }

    /**
     * Suche über mehrere Spalten
     */
    public function searchColumns(array $columns): static
    {
        $this->searchColumns = $columns;
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::TEXT;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::CONTAINS,
            MatchModeEnum::STARTS_WITH,
            MatchModeEnum::ENDS_WITH,
            MatchModeEnum::IS,
            MatchModeEnum::IS_NOT,
            MatchModeEnum::EMPTY,
        ];
    }
}
```

### Verwendung

```php
// Einfache Textsuche
TextFilter::make('name')
    ->label('Name')
    ->placeholder('Name eingeben...')
    ->defaultMatchMode(MatchModeEnum::CONTAINS);

// Suche über mehrere Spalten
TextFilter::make('search')
    ->label('Suche')
    ->searchColumns(['name', 'email', 'phone'])
    ->defaultMatchMode(MatchModeEnum::CONTAINS);

// Exakte Suche
TextFilter::make('code')
    ->label('Code')
    ->caseSensitive()
    ->defaultMatchMode(MatchModeEnum::IS);
```

---

## BooleanFilter

Für Ja/Nein-Werte.

### Definition

```php
<?php

namespace Ameax\Filter\Filters;

use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;

class BooleanFilter extends AbstractFilter
{
    protected string $trueLabel = 'Ja';
    protected string $falseLabel = 'Nein';
    protected bool $allowNull = false;
    protected ?string $nullLabel = null;

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;
        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;
        return $this;
    }

    public function allowNull(string $label = 'Unbekannt'): static
    {
        $this->allowNull = true;
        $this->nullLabel = $label;
        return $this;
    }

    public function type(): FilterTypeEnum
    {
        return FilterTypeEnum::BOOLEAN;
    }

    public function getDefaultMatchModes(): array
    {
        return [
            MatchModeEnum::IS,
        ];
    }

    public function getOptions(): array
    {
        $options = [
            true => $this->trueLabel,
            false => $this->falseLabel,
        ];

        if ($this->allowNull) {
            $options[null] = $this->nullLabel;
        }

        return $options;
    }
}
```

### Verwendung

```php
// Standard Boolean
BooleanFilter::make('is_active')
    ->label('Aktiv')
    ->trueLabel('Aktiv')
    ->falseLabel('Inaktiv');

// Mit Null-Option
BooleanFilter::make('is_verified')
    ->label('Verifiziert')
    ->trueLabel('Verifiziert')
    ->falseLabel('Nicht verifiziert')
    ->allowNull('Nicht geprüft');
```

---

## Enum-basierte Filter

### EnumFilter (Helper)

```php
<?php

namespace Ameax\Filter\Filters;

class EnumFilter
{
    public static function fromEnum(string $enumClass, string $key): SelectFilter|MultiSelectFilter
    {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("$enumClass is not a valid enum");
        }

        $options = collect($enumClass::cases())
            ->mapWithKeys(fn ($case) => [
                $case->value => method_exists($case, 'label')
                    ? $case->label()
                    : $case->name
            ])
            ->toArray();

        return SelectFilter::make($key)->options($options);
    }

    public static function multiFromEnum(string $enumClass, string $key): MultiSelectFilter
    {
        $filter = static::fromEnum($enumClass, $key);

        return MultiSelectFilter::make($key)
            ->options($filter->getOptions());
    }
}
```

### Verwendung

```php
// Aus Enum erstellen
EnumFilter::fromEnum(OrderStatusEnum::class, 'status')
    ->label('Bestellstatus');

// Multi-Select aus Enum
EnumFilter::multiFromEnum(TagEnum::class, 'tags')
    ->label('Tags')
    ->defaultMatchMode(MatchModeEnum::ANY);
```

---

## Filter-Typen Enum

```php
<?php

namespace Ameax\Filter\Enums;

enum FilterTypeEnum: string
{
    case SELECT = 'select';
    case MULTI_SELECT = 'multi_select';
    case NUMBER = 'number';
    case NUMBER_RANGE = 'number_range';
    case DATE = 'date';
    case DATE_RANGE = 'date_range';
    case TEXT = 'text';
    case BOOLEAN = 'boolean';

    public function isMultiValue(): bool
    {
        return match ($this) {
            self::MULTI_SELECT, self::NUMBER_RANGE, self::DATE_RANGE => true,
            default => false,
        };
    }

    public function defaultValueType(): ValueTypeEnum
    {
        return match ($this) {
            self::SELECT, self::TEXT => ValueTypeEnum::STRING,
            self::MULTI_SELECT => ValueTypeEnum::ARRAY,
            self::NUMBER, self::NUMBER_RANGE => ValueTypeEnum::NUMBER,
            self::DATE, self::DATE_RANGE => ValueTypeEnum::DATE,
            self::BOOLEAN => ValueTypeEnum::BOOLEAN,
        };
    }
}
```
