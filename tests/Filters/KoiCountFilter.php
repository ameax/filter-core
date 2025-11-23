<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\IntegerFilter;

class KoiCountFilter extends IntegerFilter
{
    public function column(): string
    {
        return 'count';
    }

    public function label(): string
    {
        return 'Koi Count';
    }
}
