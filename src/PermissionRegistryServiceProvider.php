<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry;

use Ahmdrv\PermissionRegistry\Authorization\GateManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Commands\DiffCommand;
use Ahmdrv\PermissionRegistry\Commands\ListCommand;
use Ahmdrv\PermissionRegistry\Commands\MakeResourceCommand;
use Ahmdrv\PermissionRegistry\Commands\SyncCommand;
use Ahmdrv\PermissionRegistry\Commands\ValidateCommand;
use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Registry\DefaultPermissionRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class PermissionRegistryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-permission-registry')
            ->hasConfigFile()
            ->hasCommands([
                MakeResourceCommand::class,
                ValidateCommand::class,
                ListCommand::class,
                DiffCommand::class,
                SyncCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PermissionRegistry::class, DefaultPermissionRegistry::class);
        $this->app->singleton(ManagementAuthorizer::class, GateManagementAuthorizer::class);
    }
}
