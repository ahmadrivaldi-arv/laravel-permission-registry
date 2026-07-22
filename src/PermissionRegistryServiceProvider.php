<?php

namespace Ahmdrv\PermissionRegistry;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ahmdrv\PermissionRegistry\Commands\PermissionRegistryCommand;

class PermissionRegistryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-permission-registry')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_permission_registry_table')
            ->hasCommand(PermissionRegistryCommand::class);
    }
}
