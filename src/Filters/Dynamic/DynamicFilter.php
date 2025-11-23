<?php

namespace Ameax\FilterCore\Filters\Dynamic;

/**
 * Interface for dynamically-defined filters.
 */
interface DynamicFilter
{
    /**
     * Get the unique key for this filter instance.
     */
    public function getKey(): string;
}
