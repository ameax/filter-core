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
            ->hasTranslations();
    }
}
