<?php

namespace Ameax\FilterCore\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Koi extends Model
{
    protected $fillable = [
        'status',
        'count',
        'name',
        'is_active',
    ];

    protected $casts = [
        'count' => 'integer',
        'is_active' => 'boolean',
    ];
}
