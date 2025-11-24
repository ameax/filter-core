<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\SelectFilter;

class KoiVarietyFilter extends SelectFilter
{
    public function column(): string
    {
        return 'variety';
    }

    public function options(): array
    {
        return [
            'Gosanke' => 'Gosanke',
            'Utsurimono' => 'Utsurimono',
            'Kawarimono' => 'Kawarimono',
        ];
    }

    public function nullable(): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'Koi Variety';
    }
}
