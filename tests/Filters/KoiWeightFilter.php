<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\DecimalFilter;

/**
 * Filter for Koi weight (stored as decimal in DB).
 */
class KoiWeightFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'weight';
    }

    public function label(): string
    {
        return 'Weight (kg)';
    }

    public function precision(): int
    {
        return 2;
    }

    public function min(): ?float
    {
        return 0.0;
    }

    public function max(): ?float
    {
        return 100.0;
    }
}
