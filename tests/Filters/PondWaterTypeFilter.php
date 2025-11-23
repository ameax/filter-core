<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\SelectFilter;

class PondWaterTypeFilter extends SelectFilter
{
    public function column(): string
    {
        return 'water_type';
    }

    public function options(): array
    {
        return [
            'fresh' => 'Fresh Water',
            'salt' => 'Salt Water',
            'brackish' => 'Brackish Water',
        ];
    }

    public function label(): string
    {
        return 'Water Type';
    }
}
