<?php

namespace Ameax\FilterCore;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilterCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filter-core')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigrations([
                'create_filter_presets_table',
                'create_filter_quick_presets_table',
            ]);
    }
}
