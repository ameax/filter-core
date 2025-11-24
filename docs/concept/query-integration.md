# Query-Integration

## Übersicht

Das Query-Integrations-System ermöglicht die Anwendung von Filtern auf verschiedene Datenquellen:

1. **Eloquent Builder** - Für Datenbank-Queries ✓ Implementiert
2. **Collection** - Für In-Memory-Filterung (geplant)

## Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                      QueryApplicator                             │
├─────────────────────────────────────────────────────────────────┤
│  + applySelection(Selection): self                               │
│  + applyFilter(FilterValue): self                                │
│  + applyGroup(FilterGroup): void                                 │
└───────────────────────────┬─────────────────────────────────────┘
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
┌─────────────────────────┐ ┌─────────────────────────┐
│    Eloquent Builder     │ │   Collection (geplant)  │
├─────────────────────────┤ ├─────────────────────────┤
│ - whereIn               │ │ - filter()              │
│ - whereBetween          │ │ - reject()              │
│ - whereHas              │ │                         │
└─────────────────────────┘ └─────────────────────────┘
```

---

## Verwendung mit Filterable Trait

Der empfohlene Weg ist die Verwendung des `Filterable` Traits auf dem Model:

```php
<?php

namespace App\Models;

use Ameax\FilterCore\Concerns\Filterable;
use App\Filters\UserStatusFilter;
use App\Filters\UserRoleFilter;
use App\Filters\DepartmentNameFilter;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use Filterable;

    protected static function filterResolver(): \Closure
    {
        return fn () => [
            UserStatusFilter::class,
            UserRoleFilter::class,
            DepartmentNameFilter::via('department'),
        ];
    }
}
```

### Einfache Verwendung

```php
use Ameax\FilterCore\Selections\FilterSelection;
use App\Filters\UserStatusFilter;
use App\Filters\UserRoleFilter;
use App\Models\User;

// Selection erstellen
$selection = FilterSelection::make()
    ->where(UserStatusFilter::class)->is('active')
    ->where(UserRoleFilter::class)->any(['admin', 'editor']);

// Auf Query anwenden
$users = User::query()
    ->applySelection($selection)
    ->paginate(20);
```

### Mit OR-Logik

```php
use Ameax\FilterCore\Selections\FilterSelection;
use Ameax\FilterCore\Selections\FilterGroup;

$selection = FilterSelection::make()
    ->where(UserStatusFilter::class)->is('active')
    ->orWhere(function (FilterGroup $g) {
        $g->where(UserRoleFilter::class)->is('admin');
        $g->where(UserRoleFilter::class)->is('superadmin');
    });

$users = User::query()->applySelection($selection)->get();
```

---

## QueryApplicator direkt verwenden

Für fortgeschrittene Anwendungsfälle kann der `QueryApplicator` auch direkt verwendet werden:

```php
<?php

use Ameax\FilterCore\Query\QueryApplicator;
use Ameax\FilterCore\Selections\FilterSelection;
use App\Filters\UserStatusFilter;
use App\Models\User;

$selection = FilterSelection::make()
    ->where(UserStatusFilter::class)->is('active');

$users = QueryApplicator::for(User::query())
    ->withFilters([UserStatusFilter::class])
    ->applySelection($selection)
    ->getQuery()
    ->get();
```

---

## Relation-Handling

Filter können über Relationen angewendet werden:

```php
// Filter-Definition
class DepartmentNameFilter extends TextFilter
{
    public function column(): string
    {
        return 'name';
    }

    // ...
}

// Verwendung im Model
protected static function filterResolver(): \Closure
{
    return fn () => [
        DepartmentNameFilter::via('department'),  // whereHas
    ];
}

// Anwendung
$selection = FilterSelection::make()
    ->where(DepartmentNameFilter::class)->contains('Engineering');

// Generiert: WHERE EXISTS (SELECT * FROM departments WHERE ... AND name LIKE '%Engineering%')
$users = User::query()->applySelection($selection)->get();
```

### Relation-Modi

```php
// Findet User die ein Department MIT dem Namen haben
DepartmentNameFilter::via('department')

// Findet User die KEIN Department mit dem Namen haben
DepartmentNameFilter::viaDoesntHave('department')

// Findet User die GAR KEIN Department haben
SomeFilter::withoutRelation('department')
```

---

## Validierung

### Selection vor Anwendung validieren

```php
$selection = FilterSelection::make()
    ->where(UserStatusFilter::class)->is('active')
    ->where(UnknownFilter::class)->is('value');

// Validierung
$result = User::validateSelection($selection);
// [
//     'valid' => false,
//     'unknown' => ['UnknownFilter'],
//     'known' => ['UserStatusFilter']
// ]
```

### Strict vs. Non-Strict Modus

```php
// Strict (default): Wirft Exception bei unbekannten Filtern
$users = User::query()->applySelection($selection)->get();

// Non-Strict: Ignoriert unbekannte Filter
$users = User::query()->applySelection($selection, strict: false)->get();
```

---

## Debugging

```php
// SQL ausgeben
$query = User::query()->applySelection($selection);
$query->toSql();      // Mit Platzhaltern
$query->toRawSql();   // Mit eingesetzten Werten
```

---

## Performance-Tipps

### Eager Loading

```php
$users = User::query()
    ->with(['department', 'roles'])  // Eager load relations
    ->applySelection($selection)
    ->get();
```

### Index-Hints (MySQL)

```php
use Illuminate\Support\Facades\DB;

$query = User::query()
    ->from(DB::raw('users USE INDEX (idx_status_created)'));

$users = $query->applySelection($selection)->get();
```
