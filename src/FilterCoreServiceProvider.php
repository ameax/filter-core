<?php

namespace Ameax\FilterCore;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ameax\FilterCore\Commands\FilterCoreCommand;

class FilterCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('filter-core')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_filter_core_table')
            ->hasCommand(FilterCoreCommand::class);
    }
}
