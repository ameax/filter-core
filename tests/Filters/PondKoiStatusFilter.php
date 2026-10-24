<?php

namespace Ameax\FilterCore\Tests\Filters;

/** The status of a pond's koi: a filter that belongs to the pond's 'kois' relation. */
class PondKoiStatusFilter extends KoiStatusFilter
{
    public function relationScope(): ?string
    {
        return 'kois';
    }
}
