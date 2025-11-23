<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\TextFilter;

class KoiNameFilter extends TextFilter
{
    public function column(): string
    {
        return 'name';
    }

    public function label(): string
    {
        return 'Koi Name';
    }
}
