<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\DecimalFilter;

/**
 * Filter for Koi price (stored as integer/cents in DB).
 *
 * User enters: 19.99
 * DB stores: 1999
 */
class KoiPriceFilter extends DecimalFilter
{
    public function column(): string
    {
        return 'price_cents';
    }

    public function label(): string
    {
        return 'Price';
    }

    public function precision(): int
    {
        return 2;
    }

    public function storedAsInteger(): bool
    {
        return true;
    }

    public function min(): ?float
    {
        return 0.0;
    }

    public function max(): ?float
    {
        return 99999.99;
    }
}
