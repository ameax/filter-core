# TODO: Database Persistence for Filter Selections

**Priority:** Medium
**Status:** Open (Concept Exists)

## Problem

FilterSelection supports JSON serialization (`toJson()` / `fromJson()`), but there's no built-in way to persist selections to a database with:
- User ownership
- Naming and descriptions
- Sharing/visibility controls
- Model scoping (which model does this selection apply to?)

Currently developers must manually create their own table and model:

```php
// Manual approach
$json = $selection->toJson();
DB::table('saved_filters')->insert(['json' => $json]);

$json = DB::table('saved_filters')->first()->json;
$selection = FilterSelection::fromJson($json);
```

## Proposed Solution

Create an **optional** `FilterPreset` model and migration that developers can publish and use:

### Migration

```php
Schema::create('filter_presets', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('model_type'); // e.g., 'App\Models\User'
    $table->json('configuration'); // FilterSelection JSON
    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
    $table->boolean('is_public')->default(false);
    $table->timestamps();

    $table->index(['model_type', 'user_id']);
    $table->index(['model_type', 'is_public']);
});
```

### Model

```php
namespace Ameax\FilterCore\Models;

use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\Database\Eloquent\Model;

class FilterPreset extends Model
{
    protected $fillable = [
        'name',
        'description',
        'model_type',
        'configuration',
        'user_id',
        'is_public',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_public' => 'boolean',
    ];

    // Load selection
    public function toSelection(): FilterSelection
    {
        return FilterSelection::fromArray($this->configuration)
            ->name($this->name)
            ->description($this->description);
    }

    // Save from selection
    public static function fromSelection(
        FilterSelection $selection,
        string $modelType,
        ?int $userId = null
    ): self {
        return static::create([
            'name' => $selection->getName() ?? 'Untitled Filter',
            'description' => $selection->getDescription(),
            'model_type' => $modelType,
            'configuration' => $selection->toArray(),
            'user_id' => $userId,
        ]);
    }

    // Scopes
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
```

### Usage Example

```php
// Save a selection
$selection = FilterSelection::make('Active Users')
    ->description('Users with active status and count > 10')
    ->where(StatusFilter::class)->is('active')
    ->where(CountFilter::class)->gt(10);

$preset = FilterPreset::fromSelection(
    $selection,
    User::class,
    auth()->id()
);

// Load a selection
$preset = FilterPreset::forModel(User::class)
    ->forUser(auth()->id())
    ->where('name', 'Active Users')
    ->first();

$users = User::query()
    ->applySelection($preset->toSelection())
    ->get();

// List available presets for a model
$presets = FilterPreset::forModel(User::class)
    ->where(fn($q) => $q->forUser(auth()->id())->orWhere->public())
    ->get();
```

## Implementation Approach

**Option 1: Built-in (Opinionated)**
- Include migration and model in filter-core
- Developers run: `php artisan vendor:publish --tag=filter-core-migrations`
- Pros: Batteries included, consistent API
- Cons: Forces a specific table structure

**Option 2: Optional Package**
- Create separate `ameax/filter-presets` package
- Depends on filter-core
- Pros: Core stays lightweight, flexibility
- Cons: Extra package to install

**Option 3: Example/Stub Only**
- Provide example migration and model in docs
- Developers copy and customize
- Pros: Maximum flexibility
- Cons: No standardization, more work for developers

**Recommendation:** Option 1 (Built-in with publishable migration) for consistency, but make it truly optional - the core package works fine without it.

## Integration with UI Packages

This would enable the planned UI features from `docs/concept/ui-adapters.md`:

- **filter-livewire**: "Save" button in FilterPanel → stores to FilterPreset
- **filter-livewire**: "Load" dropdown → lists FilterPresets for current model
- **filter-filament**: SaveSelectionAction / LoadSelectionAction table actions

```php
// In Livewire FilterPanel
public function saveSelection()
{
    FilterPreset::fromSelection(
        $this->buildSelection(),
        $this->modelType,
        auth()->id()
    );

    $this->dispatch('filter-saved');
}

public function loadSelection(int $presetId)
{
    $preset = FilterPreset::find($presetId);
    $selection = $preset->toSelection();

    // Apply to component state
    $this->filters = /* convert selection back to component state */;
}
```

## Related Files

- `src/Selections/FilterSelection.php` - Already has `toJson()` / `fromJson()`
- `docs/concept/ui-adapters.md` - Mentions SaveSelectionAction / LoadSelectionAction
- `CODEBASE_ANALYSIS.md:991-997` - Shows manual DB storage example

## References

- Commit 4ba9026: "Add FilterSelection for persisting and loading filter configurations"
- UI Adapters Concept: Actions for SaveSelectionAction.php and LoadSelectionAction.php
