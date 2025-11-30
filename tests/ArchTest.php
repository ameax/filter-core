<?php

use Ameax\FilterCore\Selections\FilterSelection;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring(FilterSelection::class); // FilterSelection provides intentional debugging tools
