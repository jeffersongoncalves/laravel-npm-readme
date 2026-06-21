<?php

declare(strict_types=1);

namespace JeffersonGoncalves\NpmReadme;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NpmReadmeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-npm-readme')
            ->hasConfigFile();
    }
}
