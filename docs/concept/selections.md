# Selektionen & Filter-Gruppen

## Übersicht

Selektionen ermöglichen die Kombination mehrerer Filter mit logischen Operatoren (AND/OR) und die Persistierung dieser Kombinationen für spätere Wiederverwendung.

## Konzepte

### FilterValue

Ein einzelner Filter-Wert mit Match-Modus:

```
FilterValue = Filter + MatchMode + Value
```

### FilterGroup

Eine Gruppe von FilterValues oder verschachtelten FilterGroups mit einem Operator:

```
FilterGroup = Operator (AND|OR) + [FilterValue | FilterGroup, ...]
```

### Selection

Eine benannte, persistierbare Sammlung von FilterGroups:

```
Selection = Name + Beschreibung + FilterGroup + Metadaten
```

---

## Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                        Selection                                 │
│  "Aktive Premium-Kunden"                                        │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              Root FilterGroup (AND)                      │    │
│  │  ┌─────────────────────────────────────────────────┐    │    │
│  │  │ FilterValue: status IS 'active'                 │    │    │
│  │  └─────────────────────────────────────────────────┘    │    │
│  │  ┌─────────────────────────────────────────────────┐    │    │
│  │  │ FilterValue: subscription ANY ['premium','ent'] │    │    │
│  │  └─────────────────────────────────────────────────┘    │    │
│  │  ┌─────────────────────────────────────────────────┐    │    │
│  │  │         Nested FilterGroup (OR)                  │    │    │
│  │  │  ┌─────────────────────────────────────────┐    │    │    │
│  │  │  │ FilterValue: created_at > 30 days ago   │    │    │    │
│  │  │  └─────────────────────────────────────────┘    │    │    │
│  │  │  ┌─────────────────────────────────────────┐    │    │    │
│  │  │  │ FilterValue: orders_count > 5           │    │    │    │
│  │  │  └─────────────────────────────────────────┘    │    │    │
│  │  └─────────────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

SQL-Äquivalent:
```sql
WHERE status = 'active'
  AND subscription IN ('premium', 'enterprise')
  AND (created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) OR orders_count > 5)
```

---

## Kern-Klassen

### GroupOperatorEnum

```php
<?php

namespace Ameax\Filter\Enums;

enum GroupOperatorEnum: string
{
    case AND = 'and';
    case OR = 'or';

    public function label(): string
    {
        return match ($this) {
            self::AND => __('filter.operator.and'),
            self::OR => __('filter.operator.or'),
        };
    }

    public function sqlKeyword(): string
    {
        return match ($this) {
            self::AND => 'AND',
            self::OR => 'OR',
        };
    }
}
```

### FilterValue

```php
<?php

namespace Ameax\Filter\Data;

use Ameax\Filter\Enums\MatchModeEnum;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class FilterValue implements Arrayable, JsonSerializable
{
    public function __construct(
        protected string $filterKey,
        protected MatchModeEnum $matchMode,
        protected mixed $value,
    ) {
    }

    public static function make(
        string $filterKey,
        MatchModeEnum $matchMode,
        mixed $value
    ): static {
        return new static($filterKey, $matchMode, $value);
    }

    public function getFilterKey(): string
    {
        return $this->filterKey;
    }

    public function getMatchMode(): MatchModeEnum
    {
        return $this->matchMode;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function withValue(mixed $value): static
    {
        return new static($this->filterKey, $this->matchMode, $value);
    }

    public function withMatchMode(MatchModeEnum $matchMode): static
    {
        return new static($this->filterKey, $matchMode, $this->value);
    }

    public function toArray(): array
    {
        return [
            'filter' => $this->filterKey,
            'mode' => $this->matchMode->value,
            'value' => $this->value,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['filter'],
            MatchModeEnum::from($data['mode']),
            $data['value'],
        );
    }
}
```

### FilterGroup

```php
<?php

namespace Ameax\Filter\Selections;

use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\GroupOperatorEnum;
use Ameax\Filter\Enums\MatchModeEnum;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class FilterGroup implements Arrayable, JsonSerializable
{
    protected GroupOperatorEnum $operator;

    /** @var array<FilterValue|FilterGroup> */
    protected array $items = [];

    public function __construct(GroupOperatorEnum $operator = GroupOperatorEnum::AND)
    {
        $this->operator = $operator;
    }

    public static function and(): static
    {
        return new static(GroupOperatorEnum::AND);
    }

    public static function or(): static
    {
        return new static(GroupOperatorEnum::OR);
    }

    public function getOperator(): GroupOperatorEnum
    {
        return $this->operator;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Fügt einen FilterValue hinzu.
     */
    public function add(FilterValue $filterValue): static
    {
        $this->items[] = $filterValue;
        return $this;
    }

    /**
     * Fügt eine verschachtelte Gruppe hinzu.
     */
    public function addGroup(FilterGroup $group): static
    {
        $this->items[] = $group;
        return $this;
    }

    /**
     * Fluent API: where('field', MatchMode, value)
     */
    public function where(string $filterKey, MatchModeEnum $matchMode, mixed $value): static
    {
        return $this->add(FilterValue::make($filterKey, $matchMode, $value));
    }

    /**
     * Fluent API: whereIs('field', value)
     */
    public function whereIs(string $filterKey, mixed $value): static
    {
        return $this->where($filterKey, MatchModeEnum::IS, $value);
    }

    /**
     * Fluent API: whereAny('field', [values])
     */
    public function whereAny(string $filterKey, array $values): static
    {
        return $this->where($filterKey, MatchModeEnum::ANY, $values);
    }

    /**
     * Fluent API: whereAll('field', [values])
     */
    public function whereAll(string $filterKey, array $values): static
    {
        return $this->where($filterKey, MatchModeEnum::ALL, $values);
    }

    /**
     * Fluent API: whereNone('field', [values])
     */
    public function whereNone(string $filterKey, array $values): static
    {
        return $this->where($filterKey, MatchModeEnum::NONE, $values);
    }

    /**
     * Fluent API: whereBetween('field', from, to)
     */
    public function whereBetween(string $filterKey, mixed $from, mixed $to): static
    {
        return $this->where($filterKey, MatchModeEnum::BETWEEN, ['from' => $from, 'to' => $to]);
    }

    /**
     * Fluent API: whereEmpty('field')
     */
    public function whereEmpty(string $filterKey): static
    {
        return $this->where($filterKey, MatchModeEnum::EMPTY, null);
    }

    /**
     * Fluent API: whereNotEmpty('field')
     */
    public function whereNotEmpty(string $filterKey): static
    {
        return $this->where($filterKey, MatchModeEnum::NOT_EMPTY, null);
    }

    /**
     * Fluent API: Verschachtelte OR-Gruppe
     */
    public function orWhere(callable $callback): static
    {
        $group = FilterGroup::or();
        $callback($group);
        return $this->addGroup($group);
    }

    /**
     * Fluent API: Verschachtelte AND-Gruppe
     */
    public function andWhere(callable $callback): static
    {
        $group = FilterGroup::and();
        $callback($group);
        return $this->addGroup($group);
    }

    public function toArray(): array
    {
        return [
            'operator' => $this->operator->value,
            'items' => array_map(fn ($item) => $item->toArray(), $this->items),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): static
    {
        $group = new static(GroupOperatorEnum::from($data['operator']));

        foreach ($data['items'] as $item) {
            if (isset($item['operator'])) {
                $group->addGroup(static::fromArray($item));
            } else {
                $group->add(FilterValue::fromArray($item));
            }
        }

        return $group;
    }
}
```

### Selection

```php
<?php

namespace Ameax\Filter\Selections;

use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\GroupOperatorEnum;
use Ameax\Filter\Enums\MatchModeEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;

class Selection implements Arrayable, JsonSerializable
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $description = null;
    protected FilterGroup $group;
    protected array $meta = [];
    protected ?\DateTimeInterface $createdAt = null;
    protected ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->group = FilterGroup::and();
    }

    public static function make(?string $name = null): static
    {
        $selection = new static();
        $selection->name = $name;
        $selection->id = Str::uuid()->toString();
        $selection->createdAt = now();

        return $selection;
    }

    // --- Getter ---

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getGroup(): FilterGroup
    {
        return $this->group;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function isEmpty(): bool
    {
        return $this->group->isEmpty();
    }

    // --- Setter (Fluent) ---

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    // --- Filter-Delegation an Group ---

    public function add(FilterValue $filterValue): static
    {
        $this->group->add($filterValue);
        return $this;
    }

    public function where(string $filterKey, MatchModeEnum $matchMode, mixed $value): static
    {
        $this->group->where($filterKey, $matchMode, $value);
        return $this;
    }

    public function whereIs(string $filterKey, mixed $value): static
    {
        $this->group->whereIs($filterKey, $value);
        return $this;
    }

    public function whereAny(string $filterKey, array $values): static
    {
        $this->group->whereAny($filterKey, $values);
        return $this;
    }

    public function whereAll(string $filterKey, array $values): static
    {
        $this->group->whereAll($filterKey, $values);
        return $this;
    }

    public function whereNone(string $filterKey, array $values): static
    {
        $this->group->whereNone($filterKey, $values);
        return $this;
    }

    public function whereBetween(string $filterKey, mixed $from, mixed $to): static
    {
        $this->group->whereBetween($filterKey, $from, $to);
        return $this;
    }

    public function orWhere(callable $callback): static
    {
        $this->group->orWhere($callback);
        return $this;
    }

    public function andWhere(callable $callback): static
    {
        $this->group->andWhere($callback);
        return $this;
    }

    // --- Hilfsmethoden ---

    /**
     * Gibt alle FilterValues flach zurück (für einfache Iteration).
     */
    public function getFilterValues(): array
    {
        return $this->flattenGroup($this->group);
    }

    protected function flattenGroup(FilterGroup $group): array
    {
        $values = [];

        foreach ($group->getItems() as $item) {
            if ($item instanceof FilterValue) {
                $values[] = $item;
            } elseif ($item instanceof FilterGroup) {
                $values = array_merge($values, $this->flattenGroup($item));
            }
        }

        return $values;
    }

    /**
     * Prüft ob ein bestimmter Filter gesetzt ist.
     */
    public function hasFilter(string $filterKey): bool
    {
        foreach ($this->getFilterValues() as $filterValue) {
            if ($filterValue->getFilterKey() === $filterKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt den Wert eines Filters zurück (oder null).
     */
    public function getFilterValue(string $filterKey): ?FilterValue
    {
        foreach ($this->getFilterValues() as $filterValue) {
            if ($filterValue->getFilterKey() === $filterKey) {
                return $filterValue;
            }
        }

        return null;
    }

    // --- Serialisierung ---

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'group' => $this->group->toArray(),
            'meta' => $this->meta,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $data): static
    {
        $selection = new static();
        $selection->id = $data['id'] ?? Str::uuid()->toString();
        $selection->name = $data['name'] ?? null;
        $selection->description = $data['description'] ?? null;
        $selection->group = FilterGroup::fromArray($data['group']);
        $selection->meta = $data['meta'] ?? [];
        $selection->createdAt = isset($data['created_at'])
            ? new \DateTimeImmutable($data['created_at'])
            : null;
        $selection->updatedAt = isset($data['updated_at'])
            ? new \DateTimeImmutable($data['updated_at'])
            : null;

        return $selection;
    }

    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
```

---

## Persistierung

### Datenbank-Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filter_selections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            // Polymorphe Beziehung zum Besitzer (User, Team, etc.)
            $table->nullableMorphs('owner');

            // Filter-Set Identifier (z.B. 'users', 'orders')
            $table->string('filter_set');

            // Die eigentliche Selection als JSON
            $table->json('definition');

            // Metadaten
            $table->json('meta')->nullable();

            // Sharing
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indizes
            $table->index(['owner_type', 'owner_id']);
            $table->index('filter_set');
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_selections');
    }
};
```

### SelectionModel

```php
<?php

namespace Ameax\Filter\Models;

use Ameax\Filter\Selections\Selection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FilterSelection extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'filter_set',
        'definition',
        'meta',
        'is_shared',
        'is_default',
    ];

    protected $casts = [
        'definition' => 'array',
        'meta' => 'array',
        'is_shared' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Konvertiert zu Selection-Objekt.
     */
    public function toSelection(): Selection
    {
        $data = $this->definition;
        $data['id'] = $this->id;
        $data['name'] = $this->name;
        $data['description'] = $this->description;
        $data['meta'] = $this->meta ?? [];
        $data['created_at'] = $this->created_at?->toIso8601String();
        $data['updated_at'] = $this->updated_at?->toIso8601String();

        return Selection::fromArray($data);
    }

    /**
     * Erstellt aus Selection-Objekt.
     */
    public static function fromSelection(Selection $selection, string $filterSet): static
    {
        return new static([
            'id' => $selection->getId(),
            'name' => $selection->getName(),
            'description' => $selection->getDescription(),
            'filter_set' => $filterSet,
            'definition' => $selection->getGroup()->toArray(),
            'meta' => $selection->getMeta(),
        ]);
    }

    // --- Scopes ---

    public function scopeForFilterSet($query, string $filterSet)
    {
        return $query->where('filter_set', $filterSet);
    }

    public function scopeForOwner($query, Model $owner)
    {
        return $query->where('owner_type', $owner->getMorphClass())
                     ->where('owner_id', $owner->getKey());
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeAccessibleBy($query, Model $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->forOwner($user)
              ->orWhere('is_shared', true);
        });
    }
}
```

### SelectionRepository

```php
<?php

namespace Ameax\Filter\Selections;

use Ameax\Filter\Models\FilterSelection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SelectionRepository
{
    /**
     * Speichert eine Selection.
     */
    public function save(Selection $selection, string $filterSet, ?Model $owner = null): FilterSelection
    {
        $model = FilterSelection::fromSelection($selection, $filterSet);

        if ($owner) {
            $model->owner()->associate($owner);
        }

        $model->save();

        return $model;
    }

    /**
     * Aktualisiert eine bestehende Selection.
     */
    public function update(string $id, Selection $selection): FilterSelection
    {
        $model = FilterSelection::findOrFail($id);
        $model->name = $selection->getName();
        $model->description = $selection->getDescription();
        $model->definition = $selection->getGroup()->toArray();
        $model->meta = $selection->getMeta();
        $model->save();

        return $model;
    }

    /**
     * Löscht eine Selection.
     */
    public function delete(string $id): void
    {
        FilterSelection::findOrFail($id)->delete();
    }

    /**
     * Findet eine Selection nach ID.
     */
    public function find(string $id): ?Selection
    {
        $model = FilterSelection::find($id);

        return $model?->toSelection();
    }

    /**
     * Gibt alle Selections für ein Filter-Set zurück.
     */
    public function getForFilterSet(string $filterSet, ?Model $owner = null): Collection
    {
        $query = FilterSelection::forFilterSet($filterSet);

        if ($owner) {
            $query->accessibleBy($owner);
        }

        return $query->get()->map->toSelection();
    }

    /**
     * Gibt die Standard-Selection zurück.
     */
    public function getDefault(string $filterSet, ?Model $owner = null): ?Selection
    {
        $query = FilterSelection::forFilterSet($filterSet)
            ->where('is_default', true);

        if ($owner) {
            $query->forOwner($owner);
        }

        return $query->first()?->toSelection();
    }

    /**
     * Setzt eine Selection als Standard.
     */
    public function setAsDefault(string $id, string $filterSet, Model $owner): void
    {
        // Bestehenden Standard entfernen
        FilterSelection::forFilterSet($filterSet)
            ->forOwner($owner)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Neuen Standard setzen
        FilterSelection::findOrFail($id)
            ->update(['is_default' => true]);
    }
}
```

---

## Verwendungsbeispiele

### Programmatische Erstellung

```php
use Ameax\Filter\Selections\Selection;
use Ameax\Filter\Enums\MatchModeEnum;

// Einfache Selection
$selection = Selection::make('Aktive Benutzer')
    ->description('Zeigt alle aktiven Benutzer an')
    ->whereIs('status', 'active');

// Komplexe Selection mit verschachtelten Gruppen
$selection = Selection::make('Aktive Premium-Kunden')
    ->whereIs('status', 'active')
    ->whereAny('subscription', ['premium', 'enterprise'])
    ->orWhere(function ($group) {
        $group->where('created_at', MatchModeEnum::GREATER_THAN, now()->subDays(30))
              ->where('orders_count', MatchModeEnum::GREATER_THAN, 0);
    });

// Mit Metadaten
$selection = Selection::make('Meine Selektion')
    ->meta([
        'icon' => 'star',
        'color' => 'yellow',
        'sort_order' => 1,
    ]);
```

### Persistierung

```php
use Ameax\Filter\Selections\SelectionRepository;

$repository = app(SelectionRepository::class);

// Speichern
$model = $repository->save($selection, 'users', auth()->user());

// Laden
$selection = $repository->find($model->id);

// Alle für User
$selections = $repository->getForFilterSet('users', auth()->user());

// Standard-Selection
$default = $repository->getDefault('users', auth()->user());
```

### In Controller verwenden

```php
use App\Filters\UserFilterSet;
use Ameax\Filter\Selections\SelectionRepository;
use Ameax\Filter\Http\FilterRequestHandler;

class UserController extends Controller
{
    public function index(
        Request $request,
        UserFilterSet $filterSet,
        SelectionRepository $selections
    ) {
        // Selection aus Request oder gespeicherte laden
        if ($request->has('selection_id')) {
            $selection = $selections->find($request->input('selection_id'));
        } else {
            $selection = FilterRequestHandler::fromRequest($request, $filterSet);
        }

        // Anwenden
        $users = $filterSet->applyTo(User::query())
            ->apply($selection)
            ->get()
            ->paginate(20);

        // Verfügbare Selektionen für Dropdown
        $savedSelections = $selections->getForFilterSet('users', auth()->user());

        return view('users.index', [
            'users' => $users,
            'filterSet' => $filterSet,
            'currentSelection' => $selection,
            'savedSelections' => $savedSelections,
        ]);
    }

    public function saveSelection(Request $request, SelectionRepository $selections)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'filters' => 'required|array',
        ]);

        $selection = Selection::make($validated['name'])
            ->description($validated['description'] ?? '');

        // Filter aus Request hinzufügen
        foreach ($validated['filters'] as $filterData) {
            $selection->add(FilterValue::fromArray($filterData));
        }

        $model = $selections->save($selection, 'users', auth()->user());

        return response()->json([
            'id' => $model->id,
            'message' => 'Selection gespeichert',
        ]);
    }
}
```

---

## JSON-Struktur

Eine persistierte Selection sieht als JSON so aus:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Aktive Premium-Kunden",
  "description": "Kunden mit aktivem Premium- oder Enterprise-Abo",
  "group": {
    "operator": "and",
    "items": [
      {
        "filter": "status",
        "mode": "is",
        "value": "active"
      },
      {
        "filter": "subscription",
        "mode": "any",
        "value": ["premium", "enterprise"]
      },
      {
        "operator": "or",
        "items": [
          {
            "filter": "created_at",
            "mode": "gt",
            "value": "2024-01-01"
          },
          {
            "filter": "orders_count",
            "mode": "gt",
            "value": 0
          }
        ]
      }
    ]
  },
  "meta": {
    "icon": "users",
    "color": "blue"
  },
  "created_at": "2024-03-15T10:30:00+00:00",
  "updated_at": "2024-03-15T10:30:00+00:00"
}
```

---

## Events

```php
<?php

namespace Ameax\Filter\Events;

use Ameax\Filter\Models\FilterSelection;

class SelectionCreated
{
    public function __construct(public FilterSelection $selection) {}
}

class SelectionUpdated
{
    public function __construct(public FilterSelection $selection) {}
}

class SelectionDeleted
{
    public function __construct(public FilterSelection $selection) {}
}

class SelectionApplied
{
    public function __construct(
        public Selection $selection,
        public string $filterSet,
        public int $resultCount
    ) {}
}
```

Verwendung:

```php
// In EventServiceProvider
protected $listen = [
    SelectionApplied::class => [
        LogSelectionUsage::class,
        UpdateSelectionStats::class,
    ],
];
```
