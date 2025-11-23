<?php

namespace Ameax\FilterCore\Tests\Filters;

use Ameax\FilterCore\Filters\SelectFilter;

class KoiStatusFilter extends SelectFilter
{
    public function column(): string
    {
        return 'status';
    }

    public function options(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
        ];
    }

    public function label(): string
    {
        return 'Koi Status';
    }
}
