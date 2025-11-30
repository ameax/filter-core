<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Models;

use Ameax\FilterCore\Selections\FilterSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * FilterPreset - Persistable filter configurations
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $model_type
 * @property array<string, mixed> $configuration
 * @property int|null $user_id
 * @property bool $is_public
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
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

    /**
     * Convert this preset to a FilterSelection.
     *
     * @example
     * $preset = FilterPreset::find(1);
     * $selection = $preset->toSelection();
     * $results = $selection->execute();
     */
    public function toSelection(): FilterSelection
    {
        $selection = FilterSelection::fromArray($this->configuration);

        // Override name/description from preset if not in configuration
        if ($selection->getName() === null && $this->name !== null) {
            $selection->name($this->name);
        }

        if ($selection->getDescription() === null && $this->description !== null) {
            $selection->description($this->description);
        }

        // Ensure model is set (from configuration or preset)
        if (! $selection->hasModel() && $this->model_type !== null) {
            /** @var class-string $modelType */
            $modelType = $this->model_type;
            $selection->forModel($modelType);
        }

        return $selection;
    }

    /**
     * Create a FilterPreset from a FilterSelection.
     *
     * @param  class-string|null  $modelType  Optional - uses selection's model if available
     * @param  int|null  $userId  Optional - the user who owns this preset
     * @param  bool  $isPublic  Whether this preset is publicly visible
     *
     * @example
     * $selection = FilterSelection::make('Active Users', User::class)
     *     ->where(StatusFilter::class)->is('active');
     *
     * $preset = FilterPreset::fromSelection($selection);
     * // or with explicit user
     * $preset = FilterPreset::fromSelection($selection, null, auth()->id());
     */
    public static function fromSelection(
        FilterSelection $selection,
        ?string $modelType = null,
        ?int $userId = null,
        bool $isPublic = false
    ): self {
        // Use provided modelType, or fallback to selection's model
        $resolvedModelType = $modelType ?? $selection->getModelClass();

        if ($resolvedModelType === null) {
            throw new \InvalidArgumentException(
                'FilterPreset requires a model type. Either provide $modelType parameter or use FilterSelection::forModel().'
            );
        }

        return static::create([
            'name' => $selection->getName() ?? 'Untitled Filter',
            'description' => $selection->getDescription(),
            'model_type' => $resolvedModelType,
            'configuration' => $selection->toArray(),
            'user_id' => $userId,
            'is_public' => $isPublic,
        ]);
    }

    /**
     * Scope to presets for a specific model.
     *
     * @param  Builder<FilterPreset>  $query
     * @param  class-string  $modelType
     * @return Builder<FilterPreset>
     *
     * @example
     * FilterPreset::forModel(User::class)->get();
     */
    public function scopeForModel(Builder $query, string $modelType): Builder
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to presets owned by a specific user.
     *
     * @param  Builder<FilterPreset>  $query
     * @return Builder<FilterPreset>
     *
     * @example
     * FilterPreset::forUser(auth()->id())->get();
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to public presets.
     *
     * @param  Builder<FilterPreset>  $query
     * @return Builder<FilterPreset>
     *
     * @example
     * FilterPreset::public()->get();
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to presets accessible by a user (owned by them or public).
     *
     * @param  Builder<FilterPreset>  $query
     * @return Builder<FilterPreset>
     *
     * @example
     * FilterPreset::forModel(User::class)
     *     ->accessibleBy(auth()->id())
     *     ->get();
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('user_id', $userId)
                ->orWhere('is_public', true);
        });
    }

    /**
     * Get the user who owns this preset.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // Use config for User model to allow customization
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('filter-core.user_model', \Illuminate\Foundation\Auth\User::class);

        return $this->belongsTo($userModel);
    }
}
