<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\BooleanFilter;

class PondHeatedFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'is_heated';
    }

    public function label(): string
    {
        return 'Is Heated';
    }
}
