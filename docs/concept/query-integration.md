# Query-Integration

## Übersicht

Das Query-Integrations-System ermöglicht die Anwendung von Filtern auf verschiedene Datenquellen:

1. **Eloquent Builder** - Für Datenbank-Queries
2. **Collection** - Für In-Memory-Filterung

## Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                      QueryApplicator                             │
├─────────────────────────────────────────────────────────────────┤
│  + apply(query, Selection): Query                                │
│  + applyFilter(query, FilterValue): Query                        │
│  + applyGroup(query, FilterGroup): Query                         │
└───────────────────────────┬─────────────────────────────────────┘
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
┌─────────────────────────┐ ┌─────────────────────────┐
│    EloquentAdapter      │ │   CollectionAdapter     │
├─────────────────────────┤ ├─────────────────────────┤
│ - whereIn               │ │ - filter()              │
│ - whereBetween          │ │ - reject()              │
│ - whereHas              │ │                         │
└─────────────────────────┘ └─────────────────────────┘
```

---

## QueryApplicator (Hauptklasse)

```php
<?php

namespace Ameax\Filter\Query;

use Ameax\Filter\Contracts\FilterContract;
use Ameax\Filter\Contracts\QueryApplicatorContract;
use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\GroupOperatorEnum;
use Ameax\Filter\Selections\FilterGroup;
use Ameax\Filter\Selections\Selection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class QueryApplicator implements QueryApplicatorContract
{
    protected array $filters = [];
    protected array $appliedFilters = [];

    public function __construct(
        protected Builder|QueryBuilder|Collection $query,
        protected array $filterDefinitions = [],
    ) {
    }

    /**
     * Erstellt einen neuen QueryApplicator.
     */
    public static function for(Builder|QueryBuilder|Collection $query): static
    {
        return new static($query);
    }

    /**
     * Registriert Filter-Definitionen.
     */
    public function withFilters(array $filters): static
    {
        foreach ($filters as $filter) {
            if ($filter instanceof FilterContract) {
                $this->filterDefinitions[$filter->getKey()] = $filter;
            }
        }

        return $this;
    }

    /**
     * Wendet eine Selection (Filter-Gruppe) an.
     */
    public function apply(Selection $selection): static
    {
        $this->applyGroup($selection->getGroup());

        return $this;
    }

    /**
     * Wendet einen einzelnen FilterValue an.
     */
    public function applyFilter(FilterValue $filterValue): static
    {
        $filterKey = $filterValue->getFilterKey();
        $filterDefinition = $this->filterDefinitions[$filterKey] ?? null;

        if ($filterDefinition === null) {
            throw new \InvalidArgumentException("Filter '{$filterKey}' is not defined");
        }

        $matchMode = $filterValue->getMatchMode();
        $value = $filterValue->getValue();
        $column = $filterDefinition->getColumn();

        // Optionen für Match-Mode sammeln
        $options = $this->buildMatchModeOptions($filterDefinition);

        // Match-Mode anwenden
        $handler = app($matchMode->handler());

        if ($this->query instanceof Collection) {
            $this->query = $handler->applyToCollection($this->query, $column, $value, $options);
        } else {
            $this->query = $handler->apply($this->query, $column, $value, $options);
        }

        $this->appliedFilters[] = $filterValue;

        return $this;
    }

    /**
     * Wendet eine FilterGroup an (rekursiv für verschachtelte Gruppen).
     */
    public function applyGroup(FilterGroup $group): static
    {
        $operator = $group->getOperator();
        $items = $group->getItems();

        if (empty($items)) {
            return $this;
        }

        if ($this->query instanceof Collection) {
            $this->applyGroupToCollection($group);
        } else {
            $this->applyGroupToQuery($group);
        }

        return $this;
    }

    /**
     * Gibt den modifizierten Query/Collection zurück.
     */
    public function get(): Builder|QueryBuilder|Collection
    {
        return $this->query;
    }

    /**
     * Gibt die angewandten Filter zurück.
     */
    public function getAppliedFilters(): array
    {
        return $this->appliedFilters;
    }

    /**
     * Prüft ob Filter angewandt wurden.
     */
    public function hasAppliedFilters(): bool
    {
        return !empty($this->appliedFilters);
    }

    // --- Private Methoden ---

    protected function applyGroupToQuery(FilterGroup $group): void
    {
        $operator = $group->getOperator();
        $items = $group->getItems();

        $method = $operator === GroupOperatorEnum::AND ? 'where' : 'orWhere';

        $this->query->{$method}(function (Builder $query) use ($items) {
            $subApplicator = new static($query, $this->filterDefinitions);

            foreach ($items as $item) {
                if ($item instanceof FilterValue) {
                    $subApplicator->applyFilter($item);
                } elseif ($item instanceof FilterGroup) {
                    $subApplicator->applyGroup($item);
                }
            }
        });
    }

    protected function applyGroupToCollection(FilterGroup $group): void
    {
        $operator = $group->getOperator();
        $items = $group->getItems();

        if ($operator === GroupOperatorEnum::AND) {
            // AND: Alle Filter nacheinander anwenden
            foreach ($items as $item) {
                if ($item instanceof FilterValue) {
                    $this->applyFilter($item);
                } elseif ($item instanceof FilterGroup) {
                    $this->applyGroup($item);
                }
            }
        } else {
            // OR: Union der Ergebnisse
            $results = collect();

            foreach ($items as $item) {
                $subCollection = clone $this->query;
                $subApplicator = new static($subCollection, $this->filterDefinitions);

                if ($item instanceof FilterValue) {
                    $subApplicator->applyFilter($item);
                } elseif ($item instanceof FilterGroup) {
                    $subApplicator->applyGroup($item);
                }

                $results = $results->merge($subApplicator->get());
            }

            $this->query = $results->unique();
        }
    }

    protected function buildMatchModeOptions(FilterContract $filter): array
    {
        $options = [];

        // Relation-Erkennung
        if ($filter->getRelation()) {
            $options['is_relation'] = true;
            $options['relation'] = $filter->getRelation();
            $options['foreign_key'] = $filter->getColumn();
        }

        // JSON-Spalten-Erkennung (basierend auf Meta oder Column-Format)
        if ($filter->getMeta()['is_json'] ?? false) {
            $options['is_json'] = true;
        }

        // Case-Sensitivity für Text-Filter
        if ($filter->getMeta()['case_sensitive'] ?? false) {
            $options['case_sensitive'] = true;
        }

        return $options;
    }
}
```

---

## Eloquent-spezifische Features

### Relation-Handling

```php
<?php

namespace Ameax\Filter\Query\Eloquent;

use Illuminate\Database\Eloquent\Builder;

class RelationHandler
{
    /**
     * Wendet Filter auf eine Relation an.
     */
    public static function apply(
        Builder $query,
        string $relation,
        string $column,
        callable $callback
    ): Builder {
        return $query->whereHas($relation, function (Builder $q) use ($column, $callback) {
            $callback($q, $column);
        });
    }

    /**
     * Wendet Filter auf verschachtelte Relationen an (z.B. 'author.profile').
     */
    public static function applyNested(
        Builder $query,
        string $nestedRelation,
        string $column,
        callable $callback
    ): Builder {
        $parts = explode('.', $nestedRelation);
        $finalRelation = array_pop($parts);
        $path = implode('.', $parts);

        if (empty($path)) {
            return static::apply($query, $finalRelation, $column, $callback);
        }

        return $query->whereHas($path, function (Builder $q) use ($finalRelation, $column, $callback) {
            static::apply($q, $finalRelation, $column, $callback);
        });
    }

    /**
     * BelongsToMany mit Pivot-Bedingungen.
     */
    public static function applyPivot(
        Builder $query,
        string $relation,
        string $pivotColumn,
        mixed $value,
        string $operator = '='
    ): Builder {
        return $query->whereHas($relation, function (Builder $q) use ($pivotColumn, $value, $operator) {
            $q->wherePivot($pivotColumn, $operator, $value);
        });
    }
}
```

### Join-Handling

```php
<?php

namespace Ameax\Filter\Query\Eloquent;

use Illuminate\Database\Eloquent\Builder;

class JoinHandler
{
    protected array $appliedJoins = [];

    /**
     * Fügt einen Join hinzu (wenn noch nicht vorhanden).
     */
    public function addJoin(
        Builder $query,
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'inner'
    ): Builder {
        $joinKey = "{$type}:{$table}:{$first}:{$operator}:{$second}";

        if (in_array($joinKey, $this->appliedJoins, true)) {
            return $query;
        }

        $this->appliedJoins[] = $joinKey;

        return match ($type) {
            'left' => $query->leftJoin($table, $first, $operator, $second),
            'right' => $query->rightJoin($table, $first, $operator, $second),
            default => $query->join($table, $first, $operator, $second),
        };
    }

    /**
     * Fügt einen Join aus einer JoinDefinition hinzu.
     */
    public function addJoinFromDefinition(Builder $query, JoinDefinition $definition): Builder
    {
        return $this->addJoin(
            $query,
            $definition->table,
            $definition->first,
            $definition->operator,
            $definition->second,
            $definition->type
        );
    }

    /**
     * Gibt die angewandten Joins zurück.
     */
    public function getAppliedJoins(): array
    {
        return $this->appliedJoins;
    }
}
```

---

## Filter-Set Definition

Ein Filter-Set gruppiert Filter für ein bestimmtes Model/Context:

```php
<?php

namespace Ameax\Filter;

use Ameax\Filter\Contracts\FilterSetContract;

abstract class FilterSet implements FilterSetContract
{
    protected array $filters = [];

    /**
     * Definiert die verfügbaren Filter.
     */
    abstract public function filters(): array;

    /**
     * Gibt einen Filter nach Key zurück.
     */
    public function getFilter(string $key): ?FilterContract
    {
        return $this->getFilters()[$key] ?? null;
    }

    /**
     * Gibt alle Filter zurück (gecached).
     */
    public function getFilters(): array
    {
        if (empty($this->filters)) {
            foreach ($this->filters() as $filter) {
                $this->filters[$filter->getKey()] = $filter;
            }
        }

        return $this->filters;
    }

    /**
     * Erstellt einen QueryApplicator für dieses Filter-Set.
     */
    public function applyTo(Builder|Collection $query): QueryApplicator
    {
        return QueryApplicator::for($query)->withFilters($this->getFilters());
    }
}
```

### Beispiel: UserFilterSet

```php
<?php

namespace App\Filters;

use Ameax\Filter\FilterSet;
use Ameax\Filter\Filters\BooleanFilter;
use Ameax\Filter\Filters\DateRangeFilter;
use Ameax\Filter\Filters\MultiSelectFilter;
use Ameax\Filter\Filters\SelectFilter;
use Ameax\Filter\Filters\TextFilter;
use App\Models\User;
use App\Enums\UserStatusEnum;

class UserFilterSet extends FilterSet
{
    public function filters(): array
    {
        return [
            TextFilter::make('search')
                ->label('Suche')
                ->searchColumns(['name', 'email'])
                ->placeholder('Name oder E-Mail...'),

            SelectFilter::make('status')
                ->label('Status')
                ->options(UserStatusEnum::options()),

            MultiSelectFilter::make('roles')
                ->label('Rollen')
                ->relation('roles')
                ->column('id')
                ->options(fn () => Role::pluck('name', 'id')),

            BooleanFilter::make('is_verified')
                ->label('Verifiziert')
                ->trueLabel('Ja')
                ->falseLabel('Nein'),

            DateRangeFilter::make('created_at')
                ->label('Registriert')
                ->withDefaultPresets(),

            SelectFilter::make('department_id')
                ->label('Abteilung')
                ->relation('department')
                ->column('id')
                ->options(fn () => Department::pluck('name', 'id'))
                ->searchable(),
        ];
    }
}
```

---

## Verwendung

### Einfache Verwendung

```php
use App\Filters\UserFilterSet;
use App\Models\User;
use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\MatchModeEnum;
use Ameax\Filter\Selections\Selection;

// Filter-Set erstellen
$filterSet = new UserFilterSet();

// Selection erstellen
$selection = Selection::make()
    ->add(FilterValue::make('status', MatchModeEnum::IS, 'active'))
    ->add(FilterValue::make('roles', MatchModeEnum::ANY, [1, 2]));

// Auf Query anwenden
$users = $filterSet->applyTo(User::query())
    ->apply($selection)
    ->get()
    ->paginate(20);
```

### Mit Request-Daten

```php
use Ameax\Filter\Http\FilterRequestHandler;

class UserController extends Controller
{
    public function index(Request $request, UserFilterSet $filterSet)
    {
        $selection = FilterRequestHandler::fromRequest($request, $filterSet);

        $users = $filterSet->applyTo(User::query())
            ->apply($selection)
            ->get()
            ->with(['roles', 'department'])
            ->paginate(20);

        return view('users.index', [
            'users' => $users,
            'filterSet' => $filterSet,
            'selection' => $selection,
        ]);
    }
}
```

### Collection-Filterung

```php
// Für bereits geladene Daten
$users = User::all();

$filteredUsers = QueryApplicator::for($users)
    ->withFilters($filterSet->getFilters())
    ->apply($selection)
    ->get();
```

---

## Request-Handler

```php
<?php

namespace Ameax\Filter\Http;

use Ameax\Filter\Contracts\FilterSetContract;
use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\MatchModeEnum;
use Ameax\Filter\Selections\Selection;
use Illuminate\Http\Request;

class FilterRequestHandler
{
    /**
     * Erstellt eine Selection aus Request-Daten.
     *
     * Erwartet Format:
     * ?filters[status][value]=active&filters[status][mode]=is
     * ?filters[roles][value][]=1&filters[roles][value][]=2&filters[roles][mode]=any
     */
    public static function fromRequest(Request $request, FilterSetContract $filterSet): Selection
    {
        $selection = Selection::make();
        $filtersData = $request->input('filters', []);

        foreach ($filtersData as $filterKey => $data) {
            $filter = $filterSet->getFilter($filterKey);

            if ($filter === null) {
                continue;
            }

            $value = $data['value'] ?? null;
            $mode = $data['mode'] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $matchMode = $mode
                ? MatchModeEnum::tryFrom($mode)
                : $filter->getDefaultMatchMode();

            if ($matchMode === null) {
                continue;
            }

            // Validierung: Ist der Match-Mode erlaubt?
            if (!in_array($matchMode, $filter->getAllowedMatchModes(), true)) {
                continue;
            }

            $selection->add(FilterValue::make($filterKey, $matchMode, $value));
        }

        return $selection;
    }

    /**
     * Serialisiert eine Selection für URL-Parameter.
     */
    public static function toQueryString(Selection $selection): string
    {
        $params = [];

        foreach ($selection->getFilterValues() as $filterValue) {
            $key = $filterValue->getFilterKey();
            $params["filters[{$key}][value]"] = $filterValue->getValue();
            $params["filters[{$key}][mode]"] = $filterValue->getMatchMode()->value;
        }

        return http_build_query($params);
    }
}
```

---

## Debugging & Logging

```php
<?php

namespace Ameax\Filter\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

trait DebugsQueries
{
    protected bool $debugMode = false;

    public function debug(bool $debug = true): static
    {
        $this->debugMode = $debug;
        return $this;
    }

    protected function logQuery(): void
    {
        if (!$this->debugMode) {
            return;
        }

        if ($this->query instanceof Builder) {
            Log::debug('Filter Query', [
                'sql' => $this->query->toSql(),
                'bindings' => $this->query->getBindings(),
                'applied_filters' => collect($this->appliedFilters)->map(fn ($f) => [
                    'key' => $f->getFilterKey(),
                    'mode' => $f->getMatchMode()->value,
                    'value' => $f->getValue(),
                ])->toArray(),
            ]);
        }
    }

    public function toSql(): string
    {
        if ($this->query instanceof Builder) {
            return $this->query->toSql();
        }

        return '';
    }

    public function toRawSql(): string
    {
        if ($this->query instanceof Builder) {
            return $this->query->toRawSql();
        }

        return '';
    }
}
```

---

## Performance-Optimierungen

### Eager Loading basierend auf Filtern

```php
<?php

namespace Ameax\Filter\Query;

trait OptimizesQueries
{
    protected array $requiredRelations = [];

    /**
     * Ermittelt benötigte Relations basierend auf aktiven Filtern.
     */
    public function getRequiredRelations(): array
    {
        $relations = [];

        foreach ($this->appliedFilters as $filterValue) {
            $filter = $this->filterDefinitions[$filterValue->getFilterKey()] ?? null;

            if ($filter && $filter->getRelation()) {
                $relations[] = $filter->getRelation();
            }
        }

        return array_unique($relations);
    }

    /**
     * Wendet optimiertes Eager Loading an.
     */
    public function withOptimizedRelations(): static
    {
        if ($this->query instanceof Builder) {
            $this->query->with($this->getRequiredRelations());
        }

        return $this;
    }
}
```

### Index-Hints

```php
// Für große Tabellen können Index-Hints hilfreich sein
$query = User::query()
    ->from(DB::raw('users USE INDEX (idx_status_created)'));

$applicator = QueryApplicator::for($query)
    ->withFilters($filterSet->getFilters())
    ->apply($selection);
```
