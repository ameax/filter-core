<?php

namespace Ameax\FilterCore\Commands;

use Illuminate\Console\Command;

class FilterCoreCommand extends Command
{
    public $signature = 'filter-core';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
