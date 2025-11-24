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

## FilterPresenter (Core)

Die Presenter-Klasse bereitet Filter-Daten für die UI auf:

```php
<?php

namespace Ameax\Filter\Presenters;

use Ameax\Filter\Contracts\FilterContract;
use Ameax\Filter\Contracts\FilterSetContract;
use Ameax\Filter\Enums\FilterTypeEnum;
use Ameax\Filter\Enums\MatchModeEnum;
use Ameax\Filter\Selections\Selection;

class FilterPresenter
{
    public function __construct(
        protected FilterSetContract $filterSet,
        protected ?Selection $selection = null,
    ) {
    }

    /**
     * Gibt alle Filter als Array für die UI zurück.
     */
    public function toArray(): array
    {
        $filters = [];

        foreach ($this->filterSet->getFilters() as $key => $filter) {
            $filters[$key] = $this->presentFilter($filter);
        }

        return [
            'filters' => $filters,
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
    protected function presentFilter(FilterContract $filter): array
    {
        $definition = $filter->toDefinition();
        $currentValue = $this->getCurrentValue($filter->getKey());

        return [
            'key' => $definition->key,
            'type' => $definition->type->value,
            'label' => $definition->label,
            'allowedMatchModes' => $this->presentMatchModes($definition->allowedMatchModes),
            'defaultMatchMode' => $definition->defaultMatchMode->value,
            'currentMatchMode' => $currentValue?->getMatchMode()->value ?? $definition->defaultMatchMode->value,
            'currentValue' => $currentValue?->getValue() ?? $definition->defaultValue,
            'hasValue' => $currentValue !== null,
            'nullable' => $definition->nullable,
            'options' => $this->presentOptions($filter),
            'meta' => $definition->meta,
            'config' => $this->getTypeConfig($filter),
        ];
    }

    /**
     * Präsentiert Match-Modes.
     */
    protected function presentMatchModes(array $modes): array
    {
        return array_map(fn (MatchModeEnum $mode) => [
            'value' => $mode->value,
            'label' => $mode->label(),
            'symbol' => $mode->symbol(),
            'supportsMultiple' => $mode->supportsMultipleValues(),
            'requiresRange' => $mode->requiresRange(),
            'requiresNoValue' => $mode->requiresNoValue(),
        ], $modes);
    }

    /**
     * Präsentiert Options für Select-Filter.
     */
    protected function presentOptions(FilterContract $filter): array
    {
        if (!method_exists($filter, 'getOptions')) {
            return [];
        }

        $options = $filter->getOptions();

        return array_map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ], $options, array_keys($options));
    }

    /**
     * Gibt typ-spezifische Konfiguration zurück.
     */
    protected function getTypeConfig(FilterContract $filter): array
    {
        $config = [];
        $definition = $filter->toDefinition();

        switch ($definition->type) {
            case FilterTypeEnum::NUMBER:
            case FilterTypeEnum::NUMBER_RANGE:
                $config = [
                    'min' => $filter->getMin() ?? null,
                    'max' => $filter->getMax() ?? null,
                    'step' => $filter->getStep() ?? 1,
                    'decimals' => $filter->getDecimals() ?? 0,
                    'unit' => $filter->getUnit() ?? null,
                ];
                break;

            case FilterTypeEnum::DATE:
            case FilterTypeEnum::DATE_RANGE:
                $config = [
                    'withTime' => $filter->getWithTime() ?? false,
                    'displayFormat' => $filter->getDisplayFormat() ?? 'Y-m-d',
                    'presets' => $filter->getPresets() ?? [],
                ];
                break;

            case FilterTypeEnum::TEXT:
                $config = [
                    'minLength' => $filter->getMinLength() ?? null,
                    'maxLength' => $filter->getMaxLength() ?? null,
                    'placeholder' => $filter->getPlaceholder() ?? null,
                ];
                break;

            case FilterTypeEnum::SELECT:
            case FilterTypeEnum::MULTI_SELECT:
                $config = [
                    'searchable' => $filter->isSearchable() ?? false,
                    'searchUrl' => $filter->getSearchUrl() ?? null,
                ];
                break;
        }

        return $config;
    }

    /**
     * Gibt aktuelle Filter-Werte aus der Selection zurück.
     */
    protected function getCurrentValue(string $filterKey): ?FilterValue
    {
        return $this->selection?->getFilterValue($filterKey);
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

        foreach ($this->selection->getFilterValues() as $filterValue) {
            $filter = $this->filterSet->getFilter($filterValue->getFilterKey());

            if ($filter === null) {
                continue;
            }

            $active[] = [
                'key' => $filterValue->getFilterKey(),
                'label' => $filter->getLabel(),
                'matchMode' => $filterValue->getMatchMode()->value,
                'matchModeLabel' => $filterValue->getMatchMode()->label(),
                'value' => $filterValue->getValue(),
                'displayValue' => $this->formatDisplayValue($filter, $filterValue),
            ];
        }

        return $active;
    }

    /**
     * Formatiert einen Wert für die Anzeige.
     */
    protected function formatDisplayValue(FilterContract $filter, FilterValue $filterValue): string
    {
        $value = $filterValue->getValue();
        $type = $filter->toDefinition()->type;

        // Für Select-Filter: Labels statt Werte anzeigen
        if (in_array($type, [FilterTypeEnum::SELECT, FilterTypeEnum::MULTI_SELECT], true)) {
            $options = $filter->getOptions();

            if (is_array($value)) {
                return implode(', ', array_map(fn ($v) => $options[$v] ?? $v, $value));
            }

            return $options[$value] ?? $value;
        }

        // Für Range-Filter
        if (is_array($value) && isset($value['from'], $value['to'])) {
            return "{$value['from']} - {$value['to']}";
        }

        // Für Boolean
        if ($type === FilterTypeEnum::BOOLEAN) {
            return $value ? $filter->getTrueLabel() : $filter->getFalseLabel();
        }

        return (string) $value;
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

use Ameax\Filter\Contracts\FilterSetContract;
use Ameax\Filter\Presenters\FilterPresenter;
use Ameax\Filter\Selections\Selection;
use Illuminate\View\Component;

class FilterPanel extends Component
{
    public array $filterData;

    public function __construct(
        public FilterSetContract $filterSet,
        public ?Selection $selection = null,
        public string $action = '',
        public string $method = 'GET',
    ) {
        $presenter = new FilterPresenter($filterSet, $selection);
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
            @case('multi_select')
                <x-filter-blade::filters.multi-select :filter="$filter" />
                @break
            @case('date_range')
                <x-filter-blade::filters.date-range :filter="$filter" />
                @break
            @case('number')
            @case('number_range')
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
                <option value="{{ $mode['value'] }}"
                        @selected($mode['value'] === $filter['currentMatchMode'])>
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
    @if($filter['config']['searchable'] && $filter['config']['searchUrl'])
        {{-- Remote Search --}}
        <div x-data="remoteSelect('{{ $filter['config']['searchUrl'] }}')"
             class="filter-select__searchable">
            <input type="text"
                   x-model="search"
                   @input.debounce.300ms="fetchOptions()"
                   placeholder="{{ __('filter.search') }}..."
                   class="filter-select__search">
            <select name="filters[{{ $filter['key'] }}][value]"
                    class="filter-select__value">
                <option value="">{{ __('filter.select_option') }}</option>
                <template x-for="option in options" :key="option.value">
                    <option :value="option.value" x-text="option.label"></option>
                </template>
            </select>
        </div>
    @else
        {{-- Statische Options --}}
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
    @endif
</div>
```

### Verwendung

```blade
{{-- In einer View --}}
<x-filter-blade::filter-panel
    :filter-set="$filterSet"
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

use Ameax\Filter\Contracts\FilterSetContract;
use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\MatchModeEnum;
use Ameax\Filter\Presenters\FilterPresenter;
use Ameax\Filter\Selections\Selection;
use Livewire\Attributes\Url;

trait WithFilters
{
    #[Url]
    public array $filters = [];

    public ?string $activeSelectionId = null;

    protected ?Selection $selection = null;

    abstract protected function getFilterSet(): FilterSetContract;

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
        $this->activeSelectionId = null;
        $this->selection = null;
        $this->resetPage();
    }

    public function loadSelection(string $selectionId): void
    {
        $repository = app(\Ameax\Filter\Selections\SelectionRepository::class);
        $this->selection = $repository->find($selectionId);

        if ($this->selection) {
            $this->activeSelectionId = $selectionId;
            $this->filters = $this->selectionToFilters($this->selection);
        }
    }

    protected function buildSelection(): void
    {
        $this->selection = Selection::make();

        foreach ($this->filters as $key => $data) {
            if (empty($data['value'])) {
                continue;
            }

            $matchMode = MatchModeEnum::tryFrom($data['mode'] ?? 'is');

            if ($matchMode) {
                $this->selection->add(FilterValue::make($key, $matchMode, $data['value']));
            }
        }
    }

    protected function selectionToFilters(Selection $selection): array
    {
        $filters = [];

        foreach ($selection->getFilterValues() as $filterValue) {
            $filters[$filterValue->getFilterKey()] = [
                'mode' => $filterValue->getMatchMode()->value,
                'value' => $filterValue->getValue(),
            ];
        }

        return $filters;
    }

    protected function getSelection(): Selection
    {
        if ($this->selection === null) {
            $this->buildSelection();
        }

        return $this->selection;
    }

    public function getFilterPresenterProperty(): FilterPresenter
    {
        return new FilterPresenter($this->getFilterSet(), $this->getSelection());
    }
}
```

### FilterPanel Livewire-Komponente

```php
<?php

namespace Ameax\FilterLivewire\Livewire;

use Ameax\Filter\Contracts\FilterSetContract;
use Ameax\Filter\Presenters\FilterPresenter;
use Ameax\Filter\Selections\Selection;
use Ameax\Filter\Selections\SelectionRepository;
use Livewire\Component;
use Livewire\Attributes\Reactive;

class FilterPanel extends Component
{
    #[Reactive]
    public array $filters = [];

    public string $filterSetClass;
    public bool $showSaveButton = true;
    public bool $showSelectionDropdown = true;

    // Für Save-Modal
    public bool $showSaveModal = false;
    public string $selectionName = '';
    public string $selectionDescription = '';

    protected FilterSetContract $filterSet;

    public function mount(string $filterSetClass): void
    {
        $this->filterSetClass = $filterSetClass;
        $this->filterSet = app($filterSetClass);
    }

    public function getFilterSetProperty(): FilterSetContract
    {
        return app($this->filterSetClass);
    }

    public function getPresenterProperty(): array
    {
        $selection = $this->buildSelection();
        $presenter = new FilterPresenter($this->filterSet, $selection);

        return $presenter->toArray();
    }

    public function getSavedSelectionsProperty(): array
    {
        $repository = app(SelectionRepository::class);

        return $repository->getForFilterSet(
            $this->filterSetClass,
            auth()->user()
        )->toArray();
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

    public function openSaveModal(): void
    {
        $this->showSaveModal = true;
    }

    public function saveSelection(): void
    {
        $this->validate([
            'selectionName' => 'required|string|max:255',
        ]);

        $selection = $this->buildSelection();
        $selection->name($this->selectionName);
        $selection->description($this->selectionDescription);

        $repository = app(SelectionRepository::class);
        $repository->save($selection, $this->filterSetClass, auth()->user());

        $this->showSaveModal = false;
        $this->selectionName = '';
        $this->selectionDescription = '';

        $this->dispatch('selectionSaved');
    }

    protected function buildSelection(): Selection
    {
        $selection = Selection::make();

        foreach ($this->filters as $key => $data) {
            if (empty($data['value'])) {
                continue;
            }

            $matchMode = \Ameax\Filter\Enums\MatchModeEnum::tryFrom($data['mode'] ?? 'is');

            if ($matchMode) {
                $selection->add(\Ameax\Filter\Data\FilterValue::make($key, $matchMode, $data['value']));
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
    {{-- Gespeicherte Selektionen --}}
    @if($showSelectionDropdown && count($this->savedSelections) > 0)
        <flux:dropdown>
            <flux:button icon="bookmark">
                {{ __('filter.saved_selections') }}
            </flux:button>
            <flux:menu>
                @foreach($this->savedSelections as $selection)
                    <flux:menu.item wire:click="$parent.loadSelection('{{ $selection['id'] }}')">
                        {{ $selection['name'] }}
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif

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
                        {{ $active['label'] }}: {{ $active['displayValue'] }}
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

    {{-- Save Modal --}}
    <flux:modal wire:model="showSaveModal">
        <flux:modal.header>
            {{ __('filter.save_selection') }}
        </flux:modal.header>

        <div class="space-y-4">
            <flux:input
                wire:model="selectionName"
                label="{{ __('filter.selection_name') }}"
                placeholder="{{ __('filter.selection_name_placeholder') }}"
            />

            <flux:textarea
                wire:model="selectionDescription"
                label="{{ __('filter.selection_description') }}"
                rows="2"
            />
        </div>

        <flux:modal.footer>
            <flux:button wire:click="$set('showSaveModal', false)" variant="ghost">
                {{ __('filter.cancel') }}
            </flux:button>
            <flux:button wire:click="saveSelection" variant="primary">
                {{ __('filter.save') }}
            </flux:button>
        </flux:modal.footer>
    </flux:modal>
</div>
```

### Verwendung in einer Livewire-Tabelle

```php
<?php

namespace App\Livewire;

use Ameax\FilterLivewire\Traits\WithFilters;
use App\Filters\UserFilterSet;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UserTable extends Component
{
    use WithPagination, WithFilters;

    protected function getFilterSet(): FilterSetContract
    {
        return app(UserFilterSet::class);
    }

    public function render()
    {
        $query = User::query();

        // Filter anwenden
        if (!$this->getSelection()->isEmpty()) {
            $query = $this->getFilterSet()
                ->applyTo($query)
                ->apply($this->getSelection())
                ->get();
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
                :filter-set-class="App\Filters\UserFilterSet::class"
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

use Ameax\Filter\Contracts\FilterSetContract;
use Ameax\Filter\Data\FilterValue;
use Ameax\Filter\Enums\MatchModeEnum;
use Ameax\Filter\Selections\Selection;
use Filament\Tables\Filters\Filter;

trait HasAdvancedFilters
{
    protected static ?FilterSetContract $filterSet = null;

    abstract protected static function getFilterSetClass(): string;

    public static function getFilterSet(): FilterSetContract
    {
        if (static::$filterSet === null) {
            static::$filterSet = app(static::getFilterSetClass());
        }

        return static::$filterSet;
    }

    /**
     * Konvertiert Filter-Core Filter zu Filament-Filtern.
     */
    public static function getAdvancedFilters(): array
    {
        $filters = [];
        $filterSet = static::getFilterSet();

        foreach ($filterSet->getFilters() as $key => $filter) {
            $filters[] = static::convertToFilamentFilter($filter);
        }

        return $filters;
    }

    protected static function convertToFilamentFilter(\Ameax\Filter\Contracts\FilterContract $filter): Filter
    {
        $definition = $filter->toDefinition();

        return Filter::make($definition->key)
            ->label($definition->label)
            ->form(static::buildFilterForm($filter))
            ->query(function ($query, array $data) use ($filter, $definition) {
                if (empty($data['value'])) {
                    return $query;
                }

                $matchMode = MatchModeEnum::tryFrom($data['mode'] ?? $definition->defaultMatchMode->value);
                $filterValue = FilterValue::make($definition->key, $matchMode, $data['value']);

                $applicator = \Ameax\Filter\Query\QueryApplicator::for($query)
                    ->withFilters([$filter]);

                return $applicator->applyFilter($filterValue)->get();
            })
            ->indicateUsing(function (array $data) use ($filter): ?string {
                if (empty($data['value'])) {
                    return null;
                }

                return $filter->getLabel() . ': ' . static::formatIndicator($filter, $data);
            });
    }

    protected static function buildFilterForm(\Ameax\Filter\Contracts\FilterContract $filter): array
    {
        $definition = $filter->toDefinition();
        $components = [];

        // Match-Mode Select (wenn mehrere erlaubt)
        if (count($definition->allowedMatchModes) > 1) {
            $components[] = \Filament\Forms\Components\Select::make('mode')
                ->label(__('filter.match_mode'))
                ->options(collect($definition->allowedMatchModes)
                    ->mapWithKeys(fn ($mode) => [$mode->value => $mode->label()])
                    ->toArray())
                ->default($definition->defaultMatchMode->value);
        }

        // Wert-Eingabe basierend auf Typ
        $components[] = match ($definition->type) {
            \Ameax\Filter\Enums\FilterTypeEnum::SELECT => \Filament\Forms\Components\Select::make('value')
                ->label(__('filter.value'))
                ->options($filter->getOptions())
                ->searchable($filter->isSearchable() ?? false),

            \Ameax\Filter\Enums\FilterTypeEnum::MULTI_SELECT => \Filament\Forms\Components\Select::make('value')
                ->label(__('filter.value'))
                ->options($filter->getOptions())
                ->multiple()
                ->searchable($filter->isSearchable() ?? false),

            \Ameax\Filter\Enums\FilterTypeEnum::DATE_RANGE => \Filament\Forms\Components\DatePicker::make('value')
                ->label(__('filter.value')),

            \Ameax\Filter\Enums\FilterTypeEnum::NUMBER,
            \Ameax\Filter\Enums\FilterTypeEnum::NUMBER_RANGE => \Filament\Forms\Components\TextInput::make('value')
                ->label(__('filter.value'))
                ->numeric(),

            \Ameax\Filter\Enums\FilterTypeEnum::BOOLEAN => \Filament\Forms\Components\Toggle::make('value')
                ->label(__('filter.value')),

            default => \Filament\Forms\Components\TextInput::make('value')
                ->label(__('filter.value')),
        };

        return $components;
    }

    protected static function formatIndicator(\Ameax\Filter\Contracts\FilterContract $filter, array $data): string
    {
        $value = $data['value'];

        if (is_array($value)) {
            $options = $filter->getOptions();
            return implode(', ', array_map(fn ($v) => $options[$v] ?? $v, $value));
        }

        if (method_exists($filter, 'getOptions') && !empty($filter->getOptions())) {
            return $filter->getOptions()[$value] ?? $value;
        }

        return (string) $value;
    }
}
```

### Verwendung in Filament-Ressource

```php
<?php

namespace App\Filament\Resources;

use Ameax\FilterFilament\Concerns\HasAdvancedFilters;
use App\Filters\UserFilterSet;
use Filament\Resources\Resource;

class UserResource extends Resource
{
    use HasAdvancedFilters;

    protected static ?string $model = User::class;

    protected static function getFilterSetClass(): string
    {
        return UserFilterSet::class;
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
