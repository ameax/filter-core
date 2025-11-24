# UI-Adapter Konzept

> **Hinweis:** Dieses Dokument beschreibt das Konzept für zukünftige UI-Packages.
> Diese werden als separate Packages entwickelt (filter-blade, filter-livewire, filter-filament)
> und sind nicht Teil von filter-core.

## Übersicht

Das UI-Adapter-System ermöglicht die Verwendung des Filter-Cores in verschiedenen Frontend-Frameworks. Jeder Adapter übersetzt die Filter-Definitionen in die jeweilige UI-Sprache.

## Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                       filter-core                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │ FilterSet    │  │ Selection    │  │ QueryApplicator      │   │
│  │ Definitions  │  │              │  │                      │   │
│  └──────┬───────┘  └──────┬───────┘  └──────────────────────┘   │
└─────────┼─────────────────┼─────────────────────────────────────┘
          │                 │
          ▼                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FilterPresenter                               │
│  - toArray()      - toJson()      - toView()                    │
└───────────────────────────┬─────────────────────────────────────┘
                            │
         ┌──────────────────┼──────────────────┐
         ▼                  ▼                  ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  filter-blade   │ │filter-livewire  │ │ filter-filament │
│                 │ │    (Flux)       │ │                 │
│  - Blade Comps  │ │ - Livewire Comp │ │ - Filament Res  │
│  - Vanilla JS   │ │ - Alpine/Flux   │ │ - Table Filter  │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

---

## FilterPresenter (Konzept für Core)

Die Presenter-Klasse bereitet Filter-Daten für die UI auf:

```php
<?php

namespace Ameax\FilterCore\Presenters;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Ameax\FilterCore\Enums\FilterTypeEnum;
use Ameax\FilterCore\Selections\FilterSelection;

class FilterPresenter
{
    public function __construct(
        protected array $filters,
        protected ?FilterSelection $selection = null,
    ) {}

    /**
     * Gibt alle Filter als Array für die UI zurück.
     */
    public function toArray(): array
    {
        $presented = [];

        foreach ($this->filters as $filter) {
            $presented[$filter::key()] = $this->presentFilter($filter);
        }

        return [
            'filters' => $presented,
            'selection' => $this->selection?->toArray(),
            'activeFilters' => $this->getActiveFilters(),
        ];
    }

    /**
     * Gibt die Daten als JSON zurück.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Präsentiert einen einzelnen Filter.
     */
    protected function presentFilter($filter): array
    {
        $currentValue = $this->getCurrentValue($filter::key());

        return [
            'key' => $filter::key(),
            'type' => $filter->type()->value,
            'label' => $filter->label(),
            'allowedMatchModes' => $this->presentMatchModes($filter->allowedModes()),
            'defaultMatchMode' => $filter->defaultMode()->key(),
            'currentMatchMode' => $currentValue?->getMatchMode()->key() ?? $filter->defaultMode()->key(),
            'currentValue' => $currentValue?->getValue(),
            'hasValue' => $currentValue !== null,
            'nullable' => $filter->nullable(),
            'options' => $this->presentOptions($filter),
        ];
    }

    /**
     * Präsentiert Match-Modes.
     */
    protected function presentMatchModes(array $modes): array
    {
        return array_map(fn (MatchModeContract $mode) => [
            'key' => $mode->key(),
            'label' => $mode->label(),
        ], $modes);
    }

    /**
     * Präsentiert Options für Select-Filter.
     */
    protected function presentOptions($filter): array
    {
        if (!method_exists($filter, 'options')) {
            return [];
        }

        $options = $filter->options();

        return array_map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ], $options, array_keys($options));
    }

    /**
     * Gibt aktuelle Filter-Werte aus der Selection zurück.
     */
    protected function getCurrentValue(string $filterKey): ?FilterValue
    {
        return $this->selection?->get($filterKey);
    }

    /**
     * Gibt Liste der aktiven Filter zurück.
     */
    protected function getActiveFilters(): array
    {
        if ($this->selection === null) {
            return [];
        }

        $active = [];

        foreach ($this->selection->all() as $filterValue) {
            $active[] = [
                'key' => $filterValue->getFilterKey(),
                'matchMode' => $filterValue->getMatchMode()->key(),
                'value' => $filterValue->getValue(),
            ];
        }

        return $active;
    }
}
```

---

## Package: filter-blade

Einfache Blade-Komponenten für serverside-gerenderte Filter.

### Package-Struktur

```
packages/filter-blade/
├── src/
│   ├── FilterBladeServiceProvider.php
│   ├── Components/
│   │   ├── FilterPanel.php
│   │   ├── FilterItem.php
│   │   ├── SelectFilter.php
│   │   ├── MultiSelectFilter.php
│   │   ├── DateRangeFilter.php
│   │   ├── NumberFilter.php
│   │   ├── TextFilter.php
│   │   ├── BooleanFilter.php
│   │   ├── ActiveFilters.php
│   │   └── SelectionDropdown.php
│   └── View/
│       └── FilterViewComposer.php
├── resources/
│   └── views/
│       └── components/
│           ├── filter-panel.blade.php
│           ├── filter-item.blade.php
│           ├── filters/
│           │   ├── select.blade.php
│           │   ├── multi-select.blade.php
│           │   ├── date-range.blade.php
│           │   ├── number.blade.php
│           │   ├── text.blade.php
│           │   └── boolean.blade.php
│           ├── active-filters.blade.php
│           └── selection-dropdown.blade.php
└── config/
    └── filter-blade.php
```

### Beispiel-Komponenten

#### FilterPanel

```php
<?php

namespace Ameax\FilterBlade\Components;

use Ameax\FilterCore\Presenters\FilterPresenter;
use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\View\Component;

class FilterPanel extends Component
{
    public array $filterData;

    public function __construct(
        public array $filters,
        public ?FilterSelection $selection = null,
        public string $action = '',
        public string $method = 'GET',
    ) {
        $presenter = new FilterPresenter($filters, $selection);
        $this->filterData = $presenter->toArray();
    }

    public function render()
    {
        return view('filter-blade::components.filter-panel');
    }
}
```

```blade
{{-- resources/views/components/filter-panel.blade.php --}}
<div class="filter-panel" x-data="filterPanel(@js($filterData))">
    <form action="{{ $action }}" method="{{ $method }}">
        @csrf

        <div class="filter-panel__filters">
            @foreach($filterData['filters'] as $key => $filter)
                <x-filter-blade::filter-item :filter="$filter" />
            @endforeach
        </div>

        <div class="filter-panel__actions">
            <button type="submit" class="btn btn-primary">
                {{ __('filter.apply') }}
            </button>
            <button type="button" class="btn btn-secondary" @click="reset()">
                {{ __('filter.reset') }}
            </button>
        </div>
    </form>

    @if(!empty($filterData['activeFilters']))
        <x-filter-blade::active-filters :filters="$filterData['activeFilters']" />
    @endif
</div>
```

#### FilterItem (Dynamischer Typ-Switch)

```blade
{{-- resources/views/components/filter-item.blade.php --}}
@props(['filter'])

<div class="filter-item" x-data="{ expanded: {{ $filter['hasValue'] ? 'true' : 'false' }} }">
    <button type="button" @click="expanded = !expanded" class="filter-item__header">
        <span class="filter-item__label">{{ $filter['label'] }}</span>
        @if($filter['hasValue'])
            <span class="filter-item__badge">1</span>
        @endif
        <span class="filter-item__toggle" :class="{ 'is-expanded': expanded }">▼</span>
    </button>

    <div x-show="expanded" x-collapse class="filter-item__body">
        @switch($filter['type'])
            @case('select')
                <x-filter-blade::filters.select :filter="$filter" />
                @break
            @case('integer')
                <x-filter-blade::filters.number :filter="$filter" />
                @break
            @case('text')
                <x-filter-blade::filters.text :filter="$filter" />
                @break
            @case('boolean')
                <x-filter-blade::filters.boolean :filter="$filter" />
                @break
        @endswitch
    </div>
</div>
```

#### Select Filter

```blade
{{-- resources/views/components/filters/select.blade.php --}}
@props(['filter'])

<div class="filter-select">
    {{-- Match-Mode Auswahl (wenn mehrere erlaubt) --}}
    @if(count($filter['allowedMatchModes']) > 1)
        <select name="filters[{{ $filter['key'] }}][mode]" class="filter-select__mode">
            @foreach($filter['allowedMatchModes'] as $mode)
                <option value="{{ $mode['key'] }}"
                        @selected($mode['key'] === $filter['currentMatchMode'])>
                    {{ $mode['label'] }}
                </option>
            @endforeach
        </select>
    @else
        <input type="hidden"
               name="filters[{{ $filter['key'] }}][mode]"
               value="{{ $filter['currentMatchMode'] }}">
    @endif

    {{-- Wert-Auswahl --}}
    <select name="filters[{{ $filter['key'] }}][value]"
            class="filter-select__value">
        <option value="">{{ __('filter.select_option') }}</option>
        @foreach($filter['options'] as $option)
            <option value="{{ $option['value'] }}"
                    @selected($option['value'] == $filter['currentValue'])>
                {{ $option['label'] }}
            </option>
        @endforeach
    </select>
</div>
```

### Verwendung

```blade
{{-- In einer View --}}
<x-filter-blade::filter-panel
    :filters="$filters"
    :selection="$selection"
    action="{{ route('users.index') }}"
/>
```

---

## Package: filter-livewire

Interaktive Livewire-Komponenten mit Echtzeit-Filterung.

### Package-Struktur

```
packages/filter-livewire/
├── src/
│   ├── FilterLivewireServiceProvider.php
│   ├── Livewire/
│   │   ├── FilterPanel.php
│   │   ├── FilterItem.php
│   │   ├── Filters/
│   │   │   ├── SelectFilter.php
│   │   │   ├── MultiSelectFilter.php
│   │   │   ├── DateRangeFilter.php
│   │   │   └── ...
│   │   ├── ActiveFilters.php
│   │   └── SelectionManager.php
│   └── Traits/
│       └── WithFilters.php
├── resources/
│   └── views/
│       └── livewire/
│           ├── filter-panel.blade.php
│           └── ...
└── config/
    └── filter-livewire.php
```

### Trait für Livewire-Komponenten

```php
<?php

namespace Ameax\FilterLivewire\Traits;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\Presenters\FilterPresenter;
use Ameax\FilterCore\Selections\FilterSelection;
use Livewire\Attributes\Url;

trait WithFilters
{
    #[Url]
    public array $filters = [];

    protected ?FilterSelection $selection = null;

    abstract protected function getAvailableFilters(): array;

    public function mountWithFilters(): void
    {
        $this->buildSelection();
    }

    public function updatedFilters(): void
    {
        $this->buildSelection();
        $this->resetPage();
    }

    public function setFilter(string $key, string $mode, mixed $value): void
    {
        $this->filters[$key] = [
            'mode' => $mode,
            'value' => $value,
        ];

        $this->buildSelection();
        $this->resetPage();
    }

    public function removeFilter(string $key): void
    {
        unset($this->filters[$key]);
        $this->buildSelection();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filters = [];
        $this->selection = null;
        $this->resetPage();
    }

    protected function buildSelection(): void
    {
        $this->selection = FilterSelection::make();

        foreach ($this->filters as $key => $data) {
            if (empty($data['value'])) {
                continue;
            }

            if (MatchMode::has($data['mode'] ?? 'is')) {
                $matchMode = MatchMode::get($data['mode'] ?? 'is');
                $this->selection->add(FilterValue::make($key, $matchMode, $data['value']));
            }
        }
    }

    protected function getSelection(): FilterSelection
    {
        if ($this->selection === null) {
            $this->buildSelection();
        }

        return $this->selection;
    }

    public function getFilterPresenterProperty(): FilterPresenter
    {
        return new FilterPresenter($this->getAvailableFilters(), $this->getSelection());
    }
}
```

### FilterPanel Livewire-Komponente

```php
<?php

namespace Ameax\FilterLivewire\Livewire;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\MatchModes\MatchMode;
use Ameax\FilterCore\Presenters\FilterPresenter;
use Ameax\FilterCore\Selections\FilterSelection;
use Livewire\Component;
use Livewire\Attributes\Reactive;

class FilterPanel extends Component
{
    #[Reactive]
    public array $filters = [];

    public array $availableFilters = [];
    public bool $showSaveButton = true;

    // Für Save-Modal
    public bool $showSaveModal = false;
    public string $selectionName = '';
    public string $selectionDescription = '';

    public function mount(array $availableFilters): void
    {
        $this->availableFilters = $availableFilters;
    }

    public function getPresenterProperty(): array
    {
        $selection = $this->buildSelection();
        $presenter = new FilterPresenter($this->availableFilters, $selection);

        return $presenter->toArray();
    }

    public function updateFilter(string $key, string $mode, mixed $value): void
    {
        $this->filters[$key] = [
            'mode' => $mode,
            'value' => $value,
        ];

        $this->dispatch('filtersUpdated', $this->filters);
    }

    public function removeFilter(string $key): void
    {
        unset($this->filters[$key]);
        $this->dispatch('filtersUpdated', $this->filters);
    }

    public function resetFilters(): void
    {
        $this->filters = [];
        $this->dispatch('filtersUpdated', $this->filters);
    }

    protected function buildSelection(): FilterSelection
    {
        $selection = FilterSelection::make();

        foreach ($this->filters as $key => $data) {
            if (empty($data['value'])) {
                continue;
            }

            if (MatchMode::has($data['mode'] ?? 'is')) {
                $matchMode = MatchMode::get($data['mode'] ?? 'is');
                $selection->add(FilterValue::make($key, $matchMode, $data['value']));
            }
        }

        return $selection;
    }

    public function render()
    {
        return view('filter-livewire::livewire.filter-panel');
    }
}
```

### Blade-View mit Flux-Komponenten

```blade
{{-- resources/views/livewire/filter-panel.blade.php --}}
<div class="filter-panel">
    {{-- Filter-Liste --}}
    <div class="filter-panel__filters space-y-4">
        @foreach($this->presenter['filters'] as $key => $filter)
            <livewire:filter-livewire::filter-item
                :key="$key"
                :filter="$filter"
                :wire:key="'filter-'.$key"
                @updated="updateFilter"
            />
        @endforeach
    </div>

    {{-- Aktive Filter --}}
    @if(!empty($this->presenter['activeFilters']))
        <div class="filter-panel__active mt-4">
            <div class="text-sm text-gray-500 mb-2">{{ __('filter.active_filters') }}:</div>
            <div class="flex flex-wrap gap-2">
                @foreach($this->presenter['activeFilters'] as $active)
                    <flux:badge dismissible wire:click="removeFilter('{{ $active['key'] }}')">
                        {{ $active['key'] }}: {{ $active['value'] }}
                    </flux:badge>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="filter-panel__actions mt-4 flex gap-2">
        <flux:button wire:click="resetFilters" variant="ghost">
            {{ __('filter.reset') }}
        </flux:button>

        @if($showSaveButton)
            <flux:button wire:click="openSaveModal" variant="ghost" icon="bookmark">
                {{ __('filter.save') }}
            </flux:button>
        @endif
    </div>
</div>
```

### Verwendung in einer Livewire-Tabelle

```php
<?php

namespace App\Livewire;

use Ameax\FilterLivewire\Traits\WithFilters;
use App\Filters\UserStatusFilter;
use App\Filters\UserNameFilter;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UserTable extends Component
{
    use WithPagination, WithFilters;

    protected function getAvailableFilters(): array
    {
        return [
            UserStatusFilter::class,
            UserNameFilter::class,
        ];
    }

    public function render()
    {
        $query = User::query();

        // Filter anwenden
        if ($this->getSelection()->hasFilters()) {
            $query->applySelection($this->getSelection());
        }

        return view('livewire.user-table', [
            'users' => $query->paginate(20),
            'filterPresenter' => $this->filterPresenter,
        ]);
    }
}
```

```blade
{{-- resources/views/livewire/user-table.blade.php --}}
<div>
    <div class="flex gap-6">
        {{-- Filter-Panel --}}
        <div class="w-64 shrink-0">
            <livewire:filter-livewire::filter-panel
                :available-filters="$this->getAvailableFilters()"
                :filters="$filters"
                @filtersUpdated="$refresh"
            />
        </div>

        {{-- Tabelle --}}
        <div class="flex-1">
            <table class="w-full">
                {{-- ... --}}
            </table>

            {{ $users->links() }}
        </div>
    </div>
</div>
```

---

## Package: filter-filament

Integration mit Filament Admin Panel.

### Package-Struktur

```
packages/filter-filament/
├── src/
│   ├── FilterFilamentServiceProvider.php
│   ├── Concerns/
│   │   └── HasAdvancedFilters.php
│   ├── Filters/
│   │   └── AdvancedFilter.php
│   └── Actions/
│       ├── SaveSelectionAction.php
│       └── LoadSelectionAction.php
└── config/
    └── filter-filament.php
```

### Trait für Filament-Ressourcen

```php
<?php

namespace Ameax\FilterFilament\Concerns;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\MatchModes\MatchMode;
use Filament\Tables\Filters\Filter;

trait HasAdvancedFilters
{
    abstract protected static function getFilterClasses(): array;

    /**
     * Konvertiert Filter-Core Filter zu Filament-Filtern.
     */
    public static function getAdvancedFilters(): array
    {
        $filters = [];

        foreach (static::getFilterClasses() as $filterClass) {
            $filter = new $filterClass();
            $filters[] = static::convertToFilamentFilter($filter);
        }

        return $filters;
    }

    protected static function convertToFilamentFilter($filter): Filter
    {
        return Filter::make($filter::key())
            ->label($filter->label())
            ->form(static::buildFilterForm($filter))
            ->query(function ($query, array $data) use ($filter) {
                if (empty($data['value'])) {
                    return $query;
                }

                $matchMode = MatchMode::get($data['mode'] ?? $filter->defaultMode()->key());
                $filterValue = FilterValue::make($filter::key(), $matchMode, $data['value']);

                return $query->applyFilter($filterValue);
            })
            ->indicateUsing(function (array $data) use ($filter): ?string {
                if (empty($data['value'])) {
                    return null;
                }

                return $filter->label() . ': ' . $data['value'];
            });
    }

    protected static function buildFilterForm($filter): array
    {
        $components = [];

        // Match-Mode Select (wenn mehrere erlaubt)
        $allowedModes = $filter->allowedModes();
        if (count($allowedModes) > 1) {
            $components[] = \Filament\Forms\Components\Select::make('mode')
                ->label(__('filter.match_mode'))
                ->options(collect($allowedModes)
                    ->mapWithKeys(fn ($mode) => [$mode->key() => $mode->label()])
                    ->toArray())
                ->default($filter->defaultMode()->key());
        }

        // Wert-Eingabe basierend auf Typ
        $components[] = match ($filter->type()->value) {
            'select' => \Filament\Forms\Components\Select::make('value')
                ->label(__('filter.value'))
                ->options($filter->options()),

            'integer' => \Filament\Forms\Components\TextInput::make('value')
                ->label(__('filter.value'))
                ->numeric(),

            'boolean' => \Filament\Forms\Components\Toggle::make('value')
                ->label(__('filter.value')),

            default => \Filament\Forms\Components\TextInput::make('value')
                ->label(__('filter.value')),
        };

        return $components;
    }
}
```

### Verwendung in Filament-Ressource

```php
<?php

namespace App\Filament\Resources;

use Ameax\FilterFilament\Concerns\HasAdvancedFilters;
use App\Filters\UserStatusFilter;
use App\Filters\UserNameFilter;
use Filament\Resources\Resource;

class UserResource extends Resource
{
    use HasAdvancedFilters;

    protected static ?string $model = User::class;

    protected static function getFilterClasses(): array
    {
        return [
            UserStatusFilter::class,
            UserNameFilter::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ...
            ])
            ->filters([
                // Standard Filament-Filter + Advanced-Filter
                ...static::getAdvancedFilters(),
            ]);
    }
}
```

---

## Zusammenfassung

| Package | Zweck | Abhängigkeiten |
|---------|-------|----------------|
| filter-core | Kern-Logik, Filter-Definitionen, Query-Anwendung | Laravel |
| filter-blade | Server-gerenderte Blade-Komponenten | filter-core, Alpine.js |
| filter-livewire | Interaktive Livewire-Komponenten | filter-core, Livewire, Flux |
| filter-filament | Filament Admin Integration | filter-core, Filament |

Alle UI-Packages nutzen den gleichen filter-core, was bedeutet:
- Filter-Definitionen werden einmal erstellt
- Selektionen sind zwischen UIs austauschbar
- Query-Logik ist konsistent
- Einfacher Wechsel zwischen UI-Frameworks möglich
