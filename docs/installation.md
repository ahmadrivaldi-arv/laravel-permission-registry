# Installation and first setup

## Requirements

- PHP 8.3 or newer
- Laravel 11, 12, or 13
- `spatie/laravel-permission` version 6.25 or newer within major version 6

The package declares Spatie Permission as a direct dependency. If the application already uses a compatible version, Composer reuses it.

## 1. Install the package

```bash
composer require ahmdrv/laravel-permission-registry
```

Laravel package discovery registers both service providers. If package discovery is disabled in the application, register these providers manually:

```php
// bootstrap/providers.php

return [
    App\Providers\AppServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    Ahmdrv\PermissionRegistry\PermissionRegistryServiceProvider::class,
];
```

## 2. Install Spatie's persistence layer

Spatie owns all role and permission tables. This package deliberately ships no database migration.

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Review Spatie's published `config/permission.php` before migrating when the application needs UUIDs, teams, custom table names, or custom role/permission models.

## 3. Add Spatie's trait to the authenticatable model

The package does not assume `App\Models\User`. Any Eloquent authenticatable model used with `UserRoleManager`, `DirectPermissionManager`, or `AccessInspector` must use Spatie's `HasRoles` trait:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasRoles;
}
```

Ensure `config/auth.php` maps the selected guard to the provider for this model. Applications with multiple guards should also follow Spatie's guard-name conventions on their authenticatable models.

## 4. Publish the registry configuration

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

## 5. Generate the first resource

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

## 6. Validate before writing

```bash
php artisan rbac:validate
php artisan rbac:list
php artisan rbac:diff
```

Validation and listing do not write to the database. Diff only reads the permission table for the resolved guard.

## 7. Preview and synchronize

```bash
php artisan rbac:sync --dry-run
php artisan rbac:sync
```

The normal sync is additive. It creates missing Spatie permission rows, never creates roles, never assigns permissions, and never deletes existing rows unless `--prune` is explicitly requested.

## 8. Grant permissions through roles

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
