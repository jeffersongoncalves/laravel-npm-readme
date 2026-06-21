<?php

namespace JeffersonGoncalves\NpmReadme\Tests;

use JeffersonGoncalves\NpmReadme\NpmReadmeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            NpmReadmeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $configPath = __DIR__.'/../config/npm-readme.php';

        if (file_exists($configPath)) {
            $app['config']->set('npm-readme', require $configPath);
        }
    }
}
