<?php

namespace Ameax\FilterCore\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ameax\FilterCore\FilterCore
 */
class FilterCore extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ameax\FilterCore\FilterCore::class;
    }
}
