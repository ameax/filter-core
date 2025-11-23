<?php

namespace Ameax\FilterCore\Filters;

/**
 * Interface for filters that have selectable options.
 */
interface HasOptions
{
    /**
     * Get the available options for this filter.
     *
     * @return array<string|int, string>
     */
    public function options(): array;
}
