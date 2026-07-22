<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\PermissionRegistryServiceProvider;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\AllowAllAuthorizer;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $migration = include __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
        $migration->up();

        $this->app->bind(ManagementAuthorizer::class, AllowAllAuthorizer::class);
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            PermissionRegistryServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => TestUser::class]);
        $app['config']->set('permission-registry.discovery.enabled', false);
    }
}
