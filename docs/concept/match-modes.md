# Match-Modi

## Phase 1 Implementierung

**Aktive Modi:**
- `IS`, `IS_NOT` - Gleichheit
- `ANY`, `NONE` - Multi-Value (für SELECT mit mehreren Filter-Werten)
- `GREATER_THAN`, `LESS_THAN`, `BETWEEN` - Zahlenvergleich
- `CONTAINS` - Textsuche
- `EMPTY`, `NOT_EMPTY` - Null-Handling

**Phase 2 (geplant):**
- `ALL` - Alle Werte müssen passen (für MULTI_SELECT)
- `GREATER_THAN_OR_EQUAL`, `LESS_THAN_OR_EQUAL`
- `STARTS_WITH`, `ENDS_WITH`

---

## Übersicht

Match-Modi definieren **WIE** ein Filter-Wert mit den Daten verglichen wird. Jeder Match-Modus implementiert eine spezifische Vergleichslogik.

## Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                      MatchModeEnum                               │
├─────────────────────────────────────────────────────────────────┤
│  IS | IS_NOT | ANY | ALL | NONE | BETWEEN | CONTAINS | ...      │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MatchModeContract                             │
├─────────────────────────────────────────────────────────────────┤
│  + apply(Builder $query, string $column, mixed $value): Builder │
│  + applyToCollection(Collection $items, string $key, $value)    │
│  + label(): string                                               │
│  + supportsMultipleValues(): bool                                │
│  + requiresRange(): bool                                         │
└─────────────────────────────────────────────────────────────────┘
                            │
            ┌───────────────┼───────────────┐
            ▼               ▼               ▼
    ┌───────────┐   ┌───────────┐   ┌───────────┐
    │ IsMatch   │   │ AnyMatch  │   │ Between   │
    │ Mode      │   │ Mode      │   │ MatchMode │
    └───────────┘   └───────────┘   └───────────┘
```

## Match-Mode Enum

```php
<?php

namespace Ameax\Filter\Enums;

enum MatchModeEnum: string
{
    // Gleichheit
    case IS = 'is';
    case IS_NOT = 'is_not';

    // Multi-Value Logik
    case ANY = 'any';           // OR: Mindestens einer der Werte
    case ALL = 'all';           // AND: Alle Werte müssen passen
    case NONE = 'none';         // NOT IN: Keiner der Werte

    // Vergleich
    case GREATER_THAN = 'gt';
    case GREATER_THAN_OR_EQUAL = 'gte';
    case LESS_THAN = 'lt';
    case LESS_THAN_OR_EQUAL = 'lte';
    case BETWEEN = 'between';

    // Text-Matching
    case CONTAINS = 'contains';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';

    // Null-Handling
    case EMPTY = 'empty';
    case NOT_EMPTY = 'not_empty';

    public function label(): string
    {
        return match ($this) {
            self::IS => __('filter.match.is'),
            self::IS_NOT => __('filter.match.is_not'),
            self::ANY => __('filter.match.any'),
            self::ALL => __('filter.match.all'),
            self::NONE => __('filter.match.none'),
            self::GREATER_THAN => __('filter.match.greater_than'),
            self::GREATER_THAN_OR_EQUAL => __('filter.match.greater_than_or_equal'),
            self::LESS_THAN => __('filter.match.less_than'),
            self::LESS_THAN_OR_EQUAL => __('filter.match.less_than_or_equal'),
            self::BETWEEN => __('filter.match.between'),
            self::CONTAINS => __('filter.match.contains'),
            self::STARTS_WITH => __('filter.match.starts_with'),
            self::ENDS_WITH => __('filter.match.ends_with'),
            self::EMPTY => __('filter.match.empty'),
            self::NOT_EMPTY => __('filter.match.not_empty'),
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::IS => '=',
            self::IS_NOT => '≠',
            self::ANY => '∈',
            self::ALL => '⊆',
            self::NONE => '∉',
            self::GREATER_THAN => '>',
            self::GREATER_THAN_OR_EQUAL => '≥',
            self::LESS_THAN => '<',
            self::LESS_THAN_OR_EQUAL => '≤',
            self::BETWEEN => '↔',
            self::CONTAINS => '⊃',
            self::STARTS_WITH => '^',
            self::ENDS_WITH => '$',
            self::EMPTY => '∅',
            self::NOT_EMPTY => '!∅',
        };
    }

    public function handler(): string
    {
        return match ($this) {
            self::IS => \Ameax\Filter\MatchModes\IsMatchMode::class,
            self::IS_NOT => \Ameax\Filter\MatchModes\IsNotMatchMode::class,
            self::ANY => \Ameax\Filter\MatchModes\AnyMatchMode::class,
            self::ALL => \Ameax\Filter\MatchModes\AllMatchMode::class,
            self::NONE => \Ameax\Filter\MatchModes\NoneMatchMode::class,
            self::GREATER_THAN => \Ameax\Filter\MatchModes\GreaterThanMatchMode::class,
            self::GREATER_THAN_OR_EQUAL => \Ameax\Filter\MatchModes\GreaterThanOrEqualMatchMode::class,
            self::LESS_THAN => \Ameax\Filter\MatchModes\LessThanMatchMode::class,
            self::LESS_THAN_OR_EQUAL => \Ameax\Filter\MatchModes\LessThanOrEqualMatchMode::class,
            self::BETWEEN => \Ameax\Filter\MatchModes\BetweenMatchMode::class,
            self::CONTAINS => \Ameax\Filter\MatchModes\ContainsMatchMode::class,
            self::STARTS_WITH => \Ameax\Filter\MatchModes\StartsWithMatchMode::class,
            self::ENDS_WITH => \Ameax\Filter\MatchModes\EndsWithMatchMode::class,
            self::EMPTY => \Ameax\Filter\MatchModes\EmptyMatchMode::class,
            self::NOT_EMPTY => \Ameax\Filter\MatchModes\NotEmptyMatchMode::class,
        };
    }

    public function supportsMultipleValues(): bool
    {
        return match ($this) {
            self::IS, self::IS_NOT, self::ANY, self::ALL, self::NONE => true,
            default => false,
        };
    }

    public function requiresRange(): bool
    {
        return $this === self::BETWEEN;
    }

    public function requiresNoValue(): bool
    {
        return match ($this) {
            self::EMPTY, self::NOT_EMPTY => true,
            default => false,
        };
    }
}
```

---

## Match-Mode Contract

```php
<?php

namespace Ameax\Filter\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface MatchModeContract
{
    /**
     * Wende den Match-Modus auf einen Eloquent Query Builder an.
     */
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder;

    /**
     * Wende den Match-Modus auf eine Collection an.
     */
    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection;

    /**
     * Gibt zurück ob dieser Modus mehrere Werte unterstützt.
     */
    public function supportsMultipleValues(): bool;

    /**
     * Gibt zurück ob dieser Modus einen Bereich erwartet.
     */
    public function requiresRange(): bool;
}
```

---

## Basis-Implementierung

```php
<?php

namespace Ameax\Filter\MatchModes;

use Ameax\Filter\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class AbstractMatchMode implements MatchModeContract
{
    public function supportsMultipleValues(): bool
    {
        return false;
    }

    public function requiresRange(): bool
    {
        return false;
    }

    /**
     * Normalisiert den Wert zu einem Array.
     */
    protected function normalizeToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [$value];
    }

    /**
     * Extrahiert den Attributwert aus einem Collection-Item.
     */
    protected function getItemValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return data_get($item, $key);
        }

        if (is_object($item)) {
            return data_get($item, $key);
        }

        return null;
    }
}
```

---

## Gleichheits-Modi

### IsMatchMode

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IsMatchMode extends AbstractMatchMode
{
    public function supportsMultipleValues(): bool
    {
        return true;
    }

    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        $values = $this->normalizeToArray($value);

        if (count($values) === 1) {
            return $query->where($column, '=', $values[0]);
        }

        return $query->whereIn($column, $values);
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        $values = $this->normalizeToArray($value);

        return $collection->filter(function ($item) use ($key, $values) {
            $itemValue = $this->getItemValue($item, $key);
            return in_array($itemValue, $values, true);
        });
    }
}
```

### IsNotMatchMode

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IsNotMatchMode extends AbstractMatchMode
{
    public function supportsMultipleValues(): bool
    {
        return true;
    }

    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        $values = $this->normalizeToArray($value);

        if (count($values) === 1) {
            return $query->where($column, '!=', $values[0]);
        }

        return $query->whereNotIn($column, $values);
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        $values = $this->normalizeToArray($value);

        return $collection->filter(function ($item) use ($key, $values) {
            $itemValue = $this->getItemValue($item, $key);
            return !in_array($itemValue, $values, true);
        });
    }
}
```

---

## Multi-Value Modi

### AnyMatchMode (OR-Logik)

Findet Datensätze, bei denen **mindestens einer** der Werte übereinstimmt.

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnyMatchMode extends AbstractMatchMode
{
    public function supportsMultipleValues(): bool
    {
        return true;
    }

    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        $values = $this->normalizeToArray($value);

        // Für normale Spalten: WHERE column IN (...)
        if (!($options['is_json'] ?? false) && !($options['is_relation'] ?? false)) {
            return $query->whereIn($column, $values);
        }

        // Für JSON-Spalten: WHERE JSON_OVERLAPS oder JSON_CONTAINS mit OR
        if ($options['is_json'] ?? false) {
            return $query->where(function (Builder $q) use ($column, $values) {
                foreach ($values as $val) {
                    $q->orWhereJsonContains($column, $val);
                }
            });
        }

        // Für Relationen: whereHas mit OR
        if ($options['is_relation'] ?? false) {
            $relation = $options['relation'];
            $foreignKey = $options['foreign_key'] ?? 'id';

            return $query->whereHas($relation, function (Builder $q) use ($foreignKey, $values) {
                $q->whereIn($foreignKey, $values);
            });
        }

        return $query;
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        $values = $this->normalizeToArray($value);

        return $collection->filter(function ($item) use ($key, $values) {
            $itemValue = $this->getItemValue($item, $key);

            // Wenn itemValue ein Array ist (z.B. Tags)
            if (is_array($itemValue)) {
                return !empty(array_intersect($itemValue, $values));
            }

            // Einfacher Wert
            return in_array($itemValue, $values, true);
        });
    }
}
```

**SQL-Beispiel:**
```sql
-- Standard (ANY von user_id 1, 2, 3)
WHERE user_id IN (1, 2, 3)

-- JSON-Spalte (ANY von tags)
WHERE (JSON_CONTAINS(tags, '"tag1"') OR JSON_CONTAINS(tags, '"tag2"'))

-- Relation (ANY von tags über Pivot)
WHERE EXISTS (
    SELECT * FROM taggables
    WHERE taggables.taggable_id = posts.id
    AND taggables.tag_id IN (1, 2, 3)
)
```

### AllMatchMode (AND-Logik)

Findet Datensätze, bei denen **alle** Werte übereinstimmen müssen.

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AllMatchMode extends AbstractMatchMode
{
    public function supportsMultipleValues(): bool
    {
        return true;
    }

    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        $values = $this->normalizeToArray($value);

        // Für JSON-Spalten: Alle Werte müssen enthalten sein
        if ($options['is_json'] ?? false) {
            foreach ($values as $val) {
                $query->whereJsonContains($column, $val);
            }
            return $query;
        }

        // Für Relationen: Alle müssen existieren
        if ($options['is_relation'] ?? false) {
            $relation = $options['relation'];
            $foreignKey = $options['foreign_key'] ?? 'id';

            foreach ($values as $val) {
                $query->whereHas($relation, function (Builder $q) use ($foreignKey, $val) {
                    $q->where($foreignKey, $val);
                });
            }
            return $query;
        }

        // Für normale Spalten macht ALL nur bei Arrays Sinn
        // Fallback zu IS wenn nur ein Wert
        if (count($values) === 1) {
            return $query->where($column, '=', $values[0]);
        }

        // Bei mehreren Werten auf einer Nicht-Array-Spalte:
        // Unmöglich - ein Feld kann nicht gleichzeitig mehrere Werte haben
        // Daher: Keine Ergebnisse
        return $query->whereRaw('1 = 0');
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        $values = $this->normalizeToArray($value);

        return $collection->filter(function ($item) use ($key, $values) {
            $itemValue = $this->getItemValue($item, $key);

            // Wenn itemValue ein Array ist
            if (is_array($itemValue)) {
                // Alle Werte müssen enthalten sein
                return empty(array_diff($values, $itemValue));
            }

            // Einfacher Wert kann nur ALL erfüllen wenn nur ein Wert
            if (count($values) === 1) {
                return $itemValue === $values[0];
            }

            return false;
        });
    }
}
```

**SQL-Beispiel:**
```sql
-- JSON-Spalte (ALL tags müssen enthalten sein)
WHERE JSON_CONTAINS(tags, '"tag1"')
  AND JSON_CONTAINS(tags, '"tag2"')

-- Relation (ALL tags müssen zugeordnet sein)
WHERE EXISTS (SELECT * FROM taggables WHERE ... AND tag_id = 1)
  AND EXISTS (SELECT * FROM taggables WHERE ... AND tag_id = 2)
```

### NoneMatchMode (NOT IN-Logik)

Findet Datensätze, bei denen **keiner** der Werte übereinstimmt.

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NoneMatchMode extends AbstractMatchMode
{
    public function supportsMultipleValues(): bool
    {
        return true;
    }

    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        $values = $this->normalizeToArray($value);

        // Für normale Spalten
        if (!($options['is_json'] ?? false) && !($options['is_relation'] ?? false)) {
            return $query->whereNotIn($column, $values);
        }

        // Für JSON-Spalten
        if ($options['is_json'] ?? false) {
            return $query->where(function (Builder $q) use ($column, $values) {
                foreach ($values as $val) {
                    $q->whereJsonDoesntContain($column, $val);
                }
            });
        }

        // Für Relationen
        if ($options['is_relation'] ?? false) {
            $relation = $options['relation'];
            $foreignKey = $options['foreign_key'] ?? 'id';

            return $query->whereDoesntHave($relation, function (Builder $q) use ($foreignKey, $values) {
                $q->whereIn($foreignKey, $values);
            });
        }

        return $query;
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        $values = $this->normalizeToArray($value);

        return $collection->filter(function ($item) use ($key, $values) {
            $itemValue = $this->getItemValue($item, $key);

            // Wenn itemValue ein Array ist
            if (is_array($itemValue)) {
                return empty(array_intersect($itemValue, $values));
            }

            // Einfacher Wert
            return !in_array($itemValue, $values, true);
        });
    }
}
```

---

## Vergleichs-Modi

### BetweenMatchMode

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BetweenMatchMode extends AbstractMatchMode
{
    public function requiresRange(): bool
    {
        return true;
    }

    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        [$from, $to] = $this->extractRange($value);

        if ($from !== null && $to !== null) {
            return $query->whereBetween($column, [$from, $to]);
        }

        if ($from !== null) {
            return $query->where($column, '>=', $from);
        }

        if ($to !== null) {
            return $query->where($column, '<=', $to);
        }

        return $query;
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        [$from, $to] = $this->extractRange($value);

        return $collection->filter(function ($item) use ($key, $from, $to) {
            $itemValue = $this->getItemValue($item, $key);

            if ($itemValue === null) {
                return false;
            }

            if ($from !== null && $itemValue < $from) {
                return false;
            }

            if ($to !== null && $itemValue > $to) {
                return false;
            }

            return true;
        });
    }

    protected function extractRange(mixed $value): array
    {
        if (is_array($value)) {
            return [
                $value['from'] ?? $value[0] ?? null,
                $value['to'] ?? $value[1] ?? null,
            ];
        }

        return [null, null];
    }
}
```

### GreaterThan / LessThan Modi

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GreaterThanMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        return $query->where($column, '>', $value);
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        return $collection->filter(function ($item) use ($key, $value) {
            $itemValue = $this->getItemValue($item, $key);
            return $itemValue !== null && $itemValue > $value;
        });
    }
}

class GreaterThanOrEqualMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        return $query->where($column, '>=', $value);
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        return $collection->filter(function ($item) use ($key, $value) {
            $itemValue = $this->getItemValue($item, $key);
            return $itemValue !== null && $itemValue >= $value;
        });
    }
}

// LessThan und LessThanOrEqual analog...
```

---

## Text-Modi

### ContainsMatchMode

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContainsMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        $caseSensitive = $options['case_sensitive'] ?? false;

        if ($caseSensitive) {
            return $query->where($column, 'LIKE BINARY', "%{$value}%");
        }

        return $query->where($column, 'LIKE', "%{$value}%");
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        $caseSensitive = $options['case_sensitive'] ?? false;

        return $collection->filter(function ($item) use ($key, $value, $caseSensitive) {
            $itemValue = $this->getItemValue($item, $key);

            if ($itemValue === null) {
                return false;
            }

            if ($caseSensitive) {
                return Str::contains($itemValue, $value);
            }

            return Str::contains(Str::lower($itemValue), Str::lower($value));
        });
    }
}
```

### StartsWithMatchMode / EndsWithMatchMode

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StartsWithMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        return $query->where($column, 'LIKE', "{$value}%");
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        return $collection->filter(function ($item) use ($key, $value) {
            $itemValue = $this->getItemValue($item, $key);
            return $itemValue !== null && Str::startsWith(Str::lower($itemValue), Str::lower($value));
        });
    }
}

class EndsWithMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        return $query->where($column, 'LIKE', "%{$value}");
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        return $collection->filter(function ($item) use ($key, $value) {
            $itemValue = $this->getItemValue($item, $key);
            return $itemValue !== null && Str::endsWith(Str::lower($itemValue), Str::lower($value));
        });
    }
}
```

---

## Null-Modi

### EmptyMatchMode / NotEmptyMatchMode

```php
<?php

namespace Ameax\Filter\MatchModes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmptyMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        return $query->where(function (Builder $q) use ($column) {
            $q->whereNull($column)
              ->orWhere($column, '=', '');
        });
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        return $collection->filter(function ($item) use ($key) {
            $itemValue = $this->getItemValue($item, $key);
            return $itemValue === null || $itemValue === '' || $itemValue === [];
        });
    }
}

class NotEmptyMatchMode extends AbstractMatchMode
{
    public function apply(
        Builder $query,
        string $column,
        mixed $value,
        array $options = []
    ): Builder {
        return $query->where(function (Builder $q) use ($column) {
            $q->whereNotNull($column)
              ->where($column, '!=', '');
        });
    }

    public function applyToCollection(
        Collection $collection,
        string $key,
        mixed $value,
        array $options = []
    ): Collection {
        return $collection->filter(function ($item) use ($key) {
            $itemValue = $this->getItemValue($item, $key);
            return $itemValue !== null && $itemValue !== '' && $itemValue !== [];
        });
    }
}
```

---

## Übersicht: Match-Modi pro Filter-Typ

| Filter-Typ    | Empfohlene Match-Modi                                              |
|---------------|-------------------------------------------------------------------|
| Select        | IS, IS_NOT, EMPTY                                                 |
| MultiSelect   | ANY, ALL, NONE, EMPTY                                             |
| Number        | IS, GT, GTE, LT, LTE, BETWEEN, EMPTY                              |
| NumberRange   | BETWEEN                                                           |
| Date          | IS, GT, GTE, LT, LTE, BETWEEN, EMPTY                              |
| DateRange     | BETWEEN                                                           |
| Text          | CONTAINS, STARTS_WITH, ENDS_WITH, IS, IS_NOT, EMPTY               |
| Boolean       | IS                                                                |

## Sprachschlüssel (Translation Keys)

```php
// resources/lang/de/filter.php
return [
    'match' => [
        'is' => 'ist',
        'is_not' => 'ist nicht',
        'any' => 'enthält einen von',
        'all' => 'enthält alle',
        'none' => 'enthält keinen von',
        'greater_than' => 'größer als',
        'greater_than_or_equal' => 'größer oder gleich',
        'less_than' => 'kleiner als',
        'less_than_or_equal' => 'kleiner oder gleich',
        'between' => 'zwischen',
        'contains' => 'enthält',
        'starts_with' => 'beginnt mit',
        'ends_with' => 'endet mit',
        'empty' => 'ist leer',
        'not_empty' => 'ist nicht leer',
    ],
];
```
