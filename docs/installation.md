# Installation and first setup

## Requirements

- PHP 8.3 or newer
- Laravel 10, 11, 12, or 13
- `spatie/laravel-permission` version 6.10 or newer within major version 6

The package declares Spatie Permission as a direct dependency. If the application already uses a compatible version, Composer reuses it.

## 1. Install and migrate Spatie Permission first

This package owns no database tables and ships no migrations. Roles, permissions, and assignments live in Spatie's tables, so complete Spatie's setup before installing this package. Without it, `rbac:diff`, `rbac:sync`, and the management services cannot run.

Follow [Spatie's installation guide](https://spatie.be/docs/laravel-permission/v6/installation-laravel) once per application. It covers publishing Spatie's configuration and migrations, options such as UUIDs, teams, and custom table names, running the migrations, and adding the `HasRoles` trait to your user model.

When that guide is complete, confirm these requirements, which this package depends on:

- Spatie's migrations have run on the database the application uses.
- Every authenticatable model used with `UserRoleManager`, `DirectPermissionManager`, or `AccessInspector` uses Spatie's `HasRoles` trait. The package does not assume `App\Models\User`.
- `config/auth.php` maps the guard this package resolves to the provider for that model. Applications with multiple guards should follow Spatie's guard-name conventions.

## 2. Install this package

With Spatie installed and migrated, require this package:

```bash
composer require ahmdrv/laravel-permission-registry
```

Laravel package discovery registers both service providers. If package discovery is disabled, register them manually in `bootstrap/providers.php` on Laravel 11 and newer:

```php
// bootstrap/providers.php

return [
    App\Providers\AppServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    Ahmdrv\PermissionRegistry\PermissionRegistryServiceProvider::class,
];
```

On Laravel 10, add both providers to the `providers` array in `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    App\Providers\AppServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    Ahmdrv\PermissionRegistry\PermissionRegistryServiceProvider::class,
])->toArray(),
```

## 3. Publish the registry configuration

```bash
php artisan vendor:publish --tag="laravel-permission-registry-config"
```

The file is published as `config/permission-registry.php`. By default:

- the guard falls back to `auth.defaults.guard`;
- resources are discovered from `app/Authorization/Permissions`;
- the discovery namespace is `App\Authorization\Permissions`;
- direct user permissions are disabled;
- management operations are authorized through Laravel Gate.

See [Configuration](configuration.md) for every setting.

## 4. Generate the first resource

```bash
php artisan rbac:make-resource Product --key=products --group=catalog --preset=crud
```

This creates:

```text
app/Authorization/Permissions/ProductPermissionResource.php
```

The CRUD preset produces these permission names without requiring an `actions()` method:

```text
products.view_any
products.view
products.create
products.update
products.delete
```

## 5. Validate before writing

```bash
php artisan rbac:validate
php artisan rbac:list
php artisan rbac:diff
```

Validation and listing do not write to the database. Diff only reads the permission table for the resolved guard.

## 6. Preview and synchronize

```bash
php artisan rbac:sync --dry-run
php artisan rbac:sync
```

The normal sync is additive. It creates missing Spatie permission rows, never creates roles, never assigns permissions, and never deletes existing rows unless `--prune` is explicitly requested.

## 7. Grant permissions through roles

Use Spatie directly in a seeder, or use this package's secured services when an acting principal is available. The services validate code registration and guard compatibility before mutation:

```php
use Ahmdrv\PermissionRegistry\Services\RoleManager;

final class CreateCatalogRole
{
    public function __construct(private RoleManager $roles) {}

    public function handle(object $actor): void
    {
        $role = $this->roles->create($actor, 'catalog-editor');

        $this->roles->syncPermissions($actor, $role, [
            'products.view_any',
            'products.view',
            'products.create',
            'products.update',
        ]);
    }
}
```

The actor must be authorized for the configured management abilities. See [Role and user management](management.md).

## Upgrade note

After upgrading this package or changing resource code, run `rbac:validate`, `rbac:diff`, and `rbac:sync --dry-run` before applying synchronization in production.
