<?php

namespace Ameax\FilterCore\Tests\Models;

use Ameax\FilterCore\Concerns\Filterable;
use Ameax\FilterCore\Tests\Filters\PondCapacityFilter;
use Ameax\FilterCore\Tests\Filters\PondHeatedFilter;
use Ameax\FilterCore\Tests\Filters\PondWaterTypeFilter;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pond extends Model
{
    use Filterable;

    protected $fillable = [
        'water_type',
        'capacity',
        'name',
        'is_heated',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_heated' => 'boolean',
    ];

    protected static function filterResolver(): Closure
    {
        return fn () => [
            PondWaterTypeFilter::class,
            PondCapacityFilter::class,
            PondHeatedFilter::class,
        ];
    }

    public function kois(): HasMany
    {
        return $this->hasMany(Koi::class);
    }
}
