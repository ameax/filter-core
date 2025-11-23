<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\BooleanFilter;

class KoiActiveFilter extends BooleanFilter
{
    public function column(): string
    {
        return 'is_active';
    }

    public function label(): string
    {
        return 'Is Active';
    }
}
