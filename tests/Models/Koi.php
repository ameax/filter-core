<?php

namespace Ameax\FilterCore\Tests\Models;

use Ameax\FilterCore\Concerns\Filterable;
use Ameax\FilterCore\Tests\Filters\KoiActiveFilter;
use Ameax\FilterCore\Tests\Filters\KoiCountFilter;
use Ameax\FilterCore\Tests\Filters\KoiNameFilter;
use Ameax\FilterCore\Tests\Filters\KoiStatusFilter;
use Ameax\FilterCore\Tests\Filters\PondCapacityFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Koi extends Model
{
    use Filterable;

    protected $fillable = [
        'pond_id',
        'status',
        'count',
        'name',
        'is_active',
        'variety',
    ];

    protected $casts = [
        'pond_id' => 'integer',
        'count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function filterResolver(): Closure
    {
        return fn () => [
            KoiStatusFilter::class,
            KoiCountFilter::class,
            KoiNameFilter::class,
            KoiActiveFilter::class,
            // Relation filters
            PondWaterTypeFilter::via('pond'),
            PondCapacityFilter::via('pond'),
        ];
    }

    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class);
    }
}
