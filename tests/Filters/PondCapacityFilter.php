<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\IntegerFilter;

class PondCapacityFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'capacity';
    }

    public function label(): string
    {
        return 'Pond Capacity';
    }
}
