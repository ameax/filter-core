<?php

namespace Ameax\FilterCore\Tests\Filters;

/** The count of a pond's koi: a filter that belongs to the pond's 'kois' relation. */
class PondKoiCountFilter extends KoiCountFilter
{
    public function relationScope(): ?string
    {
        return 'kois';
    }
}
