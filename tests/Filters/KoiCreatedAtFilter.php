<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\DateFilter;

/** When a koi was created: a date filter with its own apply() logic, for use via('kois'). */
class KoiCreatedAtFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function label(): string
    {
        return 'Koi created at';
    }
}
