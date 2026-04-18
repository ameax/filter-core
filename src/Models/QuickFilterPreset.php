<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Models;

use Ameax\FilterCore\DateRange\DateDirection;
use Ameax\FilterCore\DateRange\DateRangeValue;
use Ameax\FilterCore\DateRange\ResolvedDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * QuickFilterPreset - User-defined quick date range presets
 *
 * Labels are auto-generated from date_range_config via DateRangeValue::toLabel().
 *
 * @property int $id
 * @property string|null $scope
 * @property array<string, mixed> $date_range_config
 * @property string|null $direction
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $label Auto-generated label
 * @property-read DateRangeValue $date_range_value
 */
class QuickFilterPreset extends Model
{
    protected $table = 'filter_quick_presets';

    protected $fillable = [
        'scope',
        'date_range_config',
        'direction',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'date_range_config' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get the DateRangeValue from config.
     */
    public function getDateRangeValueAttribute(): DateRangeValue
    {
        return DateRangeValue::fromArray($this->date_range_config);
    }

    /**
     * Get the auto-generated label.
     */
    public function getLabelAttribute(): string
    {
        return $this->date_range_value->toLabel();
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Resolve the date range to concrete start/end dates.
     */
    public function resolve(?Carbon $reference = null): ResolvedDateRange
    {
        return $this->date_range_value->resolve($reference);
    }

    /**
     * Convert to array for API response.
     *
     * @return array{id: int, label: string, config: array<string, mixed>}
     */
    public function toOptionArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'config' => $this->date_range_config,
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to active presets.
     *
     * @param  Builder<QuickFilterPreset>  $query
     * @return Builder<QuickFilterPreset>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to global presets (scope = null).
     *
     * @param  Builder<QuickFilterPreset>  $query
     * @return Builder<QuickFilterPreset>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('scope');
    }

    /**
     * Scope to global + specific scope presets.
     *
     * @param  Builder<QuickFilterPreset>  $query
     * @return Builder<QuickFilterPreset>
     */
    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where(function (Builder $q) use ($scope): void {
            $q->whereNull('scope')
                ->orWhere('scope', $scope);
        });
    }

    /**
     * Scope to global + multiple specific scopes.
     *
     * @param  Builder<QuickFilterPreset>  $query
     * @param  array<string>  $scopes
     * @return Builder<QuickFilterPreset>
     */
    public function scopeForScopes(Builder $query, array $scopes): Builder
    {
        if (empty($scopes)) {
            return $query->whereNull('scope');
        }

        return $query->where(function (Builder $q) use ($scopes): void {
            $q->whereNull('scope')
                ->orWhereIn('scope', $scopes);
        });
    }

    /**
     * Scope to presets matching direction restriction.
     *
     * @param  Builder<QuickFilterPreset>  $query
     * @param  array<DateDirection>|null  $allowedDirections  null = all directions
     * @return Builder<QuickFilterPreset>
     */
    public function scopeForDirection(Builder $query, ?array $allowedDirections): Builder
    {
        if ($allowedDirections === null) {
            return $query;
        }

        $values = array_map(fn (DateDirection $d): string => $d->value, $allowedDirections);

        return $query->where(function (Builder $q) use ($values): void {
            $q->whereNull('direction')
                ->orWhereIn('direction', $values);
        });
    }

    /**
     * Scope to order by sort_order.
     *
     * @param  Builder<QuickFilterPreset>  $query
     * @return Builder<QuickFilterPreset>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Get presets for a DateFilter with optional scopes.
     *
     * @param  array<string>  $scopes
     * @param  array<DateDirection>|null  $allowedDirections
     * @return Collection<int, static>
     */
    public static function getForFilter(array $scopes = [], ?array $allowedDirections = null): Collection
    {
        return static::query()
            ->active()
            ->forScopes($scopes)
            ->forDirection($allowedDirections)
            ->ordered()
            ->get();
    }

    /**
     * Get presets as option array for API response.
     *
     * @param  array<string>  $scopes
     * @param  array<DateDirection>|null  $allowedDirections
     * @return array<int, array{id: int, label: string, config: array<string, mixed>}>
     */
    public static function getOptionsForFilter(array $scopes = [], ?array $allowedDirections = null): array
    {
        return static::getForFilter($scopes, $allowedDirections)
            ->map(fn (QuickFilterPreset $preset): array => $preset->toOptionArray())
            ->values()
            ->all();
    }
}
