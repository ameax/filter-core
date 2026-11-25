<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\DateFilter;

/** When a pond's koi was created: a date filter that belongs to the 'kois' relation. */
class PondKoiCreatedAtFilter extends DateFilter
{
    public function column(): string
    {
        return 'created_at';
    }

    public function label(): string
    {
        return 'Koi created at';
    }

    public function relationScope(): ?string
    {
        return 'kois';
    }
}
