# Laravel Permission Registry

[![Tests](https://github.com/ahmdrv/laravel-permission-registry/actions/workflows/run-tests.yml/badge.svg)](https://github.com/ahmdrv/laravel-permission-registry/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/ahmdrv/laravel-permission-registry/actions/workflows/phpstan.yml/badge.svg)](https://github.com/ahmdrv/laravel-permission-registry/actions/workflows/phpstan.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahmdrv/laravel-permission-registry.svg?style=flat-square)](https://packagist.org/packages/ahmdrv/laravel-permission-registry)

A headless, code-first registry for defining, discovering, validating, inspecting, and synchronizing permissions backed by [spatie/laravel-permission](https://spatie.be/docs/laravel-permission). It gives authorization capabilities a deterministic source of truth while leaving persistence, Laravel Gate integration, role-derived access, and direct access calculation to Spatie.

This package does not replace Laravel authorization. It does not provide a UI, couple resources to Eloquent models, infer access, or generate policies. A permission resource is an authorization domain such as products, dashboards, reports, settings, or imports.

## Requirements and installation

- PHP 8.3 or newer
- Laravel 11, 12, or 13
- `spatie/laravel-permission` 6.25 or newer within version 6

Install both packages, publish Spatie's configuration and migrations, and migrate according to Spatie's documentation:

```bash
composer require ahmdrv/laravel-permission-registry
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

This package owns no database tables or migrations. Publish its focused configuration with:

```bash
php artisan vendor:publish --tag="laravel-permission-registry-config"
```

The defaults discover resources under `app/Authorization/Permissions`, use Laravel's default guard when `guard` is `null`, and disable direct user permissions.

## Defining resources

Generate a resource:

```bash
php artisan rbac:make-resource Product
php artisan rbac:make-resource Product --key=products --group=catalog --preset=crud
```

`--preset` accepts `crud`, `read-only`, or `none`; `--force` follows normal generator overwrite behavior. Use `--key` when Laravel's English pluralizer is unsuitable. Generated resources contain no redundant standard action declarations.

A complete resource can add an action and selectively override metadata on a preset action:

```php
<?php

declare(strict_types=1);

namespace App\Authorization\Permissions;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;

final class ProductPermissionResource extends PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::CRUD;

    public static function key(): string
    {
        return 'products';
    }

    public static function label(): string
    {
        return 'Products';
    }

    public static function group(): string
    {
        return 'catalog';
    }

    public static function description(): ?string
    {
        return 'Product catalog management';
    }

    protected static function actions(): array
    {
        return [
            PermissionAction::make('publish')
                ->label('Publish product')
                ->risk(RiskLevel::HIGH)
                ->directGrantable(),

            PermissionAction::make('delete')
                ->risk(RiskLevel::CRITICAL),
        ];
    }
}
```

This produces, in order, `products.view_any`, `products.view`, `products.create`, `products.update`, `products.delete`, and `products.publish`. Keys must match `^[a-z][a-z0-9_]*$`; dots are reserved for joining the resource and action keys.

| Preset | Standard actions |
| --- | --- |
| `CRUD` | `view_any`, `view`, `create`, `update`, `delete` |
| `READ_ONLY` | `view_any`, `view` |
| `NONE` | none |

Custom actions are additive. When their key matches a preset action, only supplied metadata is replaced. To remove an action after merging, override the protected hook:

```php
protected static function exceptActions(): array
{
    return ['delete'];
}
```

Risk levels are `LOW`, `MEDIUM`, `HIGH`, and `CRITICAL`. `directGrantable` defaults to false. Groups are generic organization metadata and carry no navigation or authorization behavior.

## Advisory recommendations

Recommendations describe useful companions and nothing more:

```php
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionRecommendation;

PermissionAction::make('export')->recommend(
    PermissionRecommendation::make('reports.view_any')
        ->reason('Useful when the user also needs to browse report history.'),
);
```

Targets must be fully qualified registered permission names. Missing targets, duplicates, and self-references fail validation after the complete registry is assembled. Direction is intentional; reverse recommendations are not added. Cycles produce warnings because recommendations are not dependencies.

Recommendations never grant, revoke, require, imply, deny, preselect, or synchronize access. They never alter Gate, policies, `$user->can()`, Blade `@can`, Spatie's effective permission union, role/user synchronization input, diff categories, or database rows. Absence of a recommendation target never blocks assigning or using the source permission.

## Registration and discovery

Discovery is configurable and can be disabled:

```php
'resources' => [
    App\Authorization\Permissions\ProductPermissionResource::class,
],
'discovery' => [
    'enabled' => true,
    'paths' => [app_path('Authorization/Permissions')],
    'namespace' => 'App\\Authorization\\Permissions',
],
```

Explicit resources work with discovery disabled. You can also register resources during application boot:

```php
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;

app(PermissionRegistry::class)->register(ProductPermissionResource::class);
```

Discovery enumerates deterministically and only loads matching `*PermissionResource.php` candidates in the configured namespace. Invalid paths, namespaces, classes, duplicate keys, malformed actions, and likely exclusion typos fail with actionable messages. There is no registry cache in version 1.

Query the normalized registry through constructor injection:

```php
public function __construct(private PermissionRegistry $registry) {}

$resources = $this->registry->resources();
$permissions = $this->registry->permissions();
$recommendations = $this->registry->recommendations();
$catalog = $this->registry->permissionsByGroup()['catalog'] ?? [];
$publish = $this->registry->findPermission('products.publish');
```

## Commands

```bash
php artisan rbac:validate
php artisan rbac:list --group=catalog --risk=high --with-recommendations
php artisan rbac:list --json
php artisan rbac:diff --guard=web
php artisan rbac:sync --dry-run
php artisan rbac:sync
php artisan rbac:sync --prune
php artisan rbac:sync --prune --force
```

`rbac:validate` performs no writes and reports recommendation cycles as warnings. `rbac:list` supports group, resource, and risk filters plus deterministic JSON.

`rbac:diff` is read-only. It classifies code-only permissions as missing, permissions in both places as synchronized, database-only names under a currently registered resource key as managed orphans, and all other database permissions as unmanaged. Recommendations never affect these categories.

`rbac:sync` validates first, resolves an explicit guard, and is additive and idempotent by default. It uses a transaction, adds only missing permission names, leaves existing and unrelated permissions untouched, then clears Spatie's cache. `--dry-run` performs no writes.

Pruning is deliberately conservative. Only a database name shaped as `{currently_registered_resource_key}.{valid_action_key}` can be deleted. Candidates are displayed first and require confirmation unless `--force` is supplied. Permissions belonging to a removed resource are treated as unmanaged because version 1 has no persistent ownership manifest; the package will not guess and delete them.

## Roles and users

Managers require an acting principal explicitly. They never call `auth()->user()`:

```php
use Ahmdrv\PermissionRegistry\Services\RoleManager;
use Ahmdrv\PermissionRegistry\Services\UserRoleManager;

$role = $roleManager->create($actor, 'catalog-editor');
$roleManager->syncPermissions($actor, $role, [
    'products.view_any',
    'products.create',
    'products.update',
]);

$userRoleManager->assign($actor, $user, 'catalog-editor');
$userRoleManager->sync($actor, $user, ['catalog-editor', 'auditor']);
```

`RoleManager` also provides `find`, `delete`, `grant`, and `revoke`; `UserRoleManager` provides `assign`, `remove`, and `sync`. Permission inputs must exist in code, and persisted roles and permissions must match the resolved guard. Multi-step mutations are transactional, cache is invalidated after success, and synchronization events are not emitted for no-ops or failures.

The default `ManagementAuthorizer` uses Laravel Gate. Its centralized abilities are configurable:

| Operation | Default ability |
| --- | --- |
| Create role | `roles.create` |
| Update role permissions | `roles.update` |
| Delete role | `roles.delete` |
| Assign user roles | `users.assign_roles` |
| Manage direct permissions | `users.manage_direct_permissions` |

The package neither defines nor grants these abilities. Bind your own `Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer` for trusted queue/deployment contexts; this explicit binding is the supported alternative to request authorization, and there is no boolean bypass.

### Direct user permissions

Direct grants require two opt-ins: `permission-registry.direct_permissions.enabled` must be true and the action must explicitly call `directGrantable()`. Role assignment ignores that metadata. Disabled direct mutations throw `DirectPermissionsDisabled` before writes, and ineligible grants throw `PermissionNotDirectGrantable`.

```php
$directPermissionManager->grant($actor, $user, 'products.publish');
$directPermissionManager->revoke($actor, $user, 'products.publish');
$directPermissionManager->sync($actor, $user, ['products.publish']);

$sources = $accessInspector->inspect($user);
// Each item reports the permission, whether it is direct, and contributing roles.
```

Revoking a direct permission does not affect the same capability inherited through a role. Effective access remains Spatie's normal union; there is no negative permission, expiry, or precedence layer.

## Laravel authorization

Use global capabilities directly in controllers or Livewire actions, and policies for record-level context:

```php
$this->authorize('products.create');
$this->authorize('update', $product); // ownership, tenant, state, or data-scope policy
```

```blade
@can('products.view_any')
    <a href="...">Products</a>
@endcan
```

Hiding UI is presentation only. Every server-side action must authorize again. The package ships no routes, controllers, views, Livewire components, JavaScript, navigation renderer, or policy generator.

Permissions remain independent. A role-assignment workflow should authorize its exact capability and may then load its workflow data:

```php
$this->authorize('users.assign_roles');

// This action may load role options specifically for this workflow.
// It does not rely on, imply, recommend by default, or grant roles.view_any.
```

Consequently `@can('roles.view_any')` remains false until that exact permission is granted.

## Version 1 non-goals

Version 1 intentionally excludes UI and form builders, model-to-resource mapping, policy generation, field permissions, ABAC/rule expressions, row-level query scopes, package-level tenancy abstractions, enforceable dependencies or implication, direct deny, expiring grants, approvals, remote synchronization, broad unprovable pruning, package-owned registry tables, and a third-party UI plugin system.

## Testing and contributing

```bash
composer validate --strict
composer test
composer analyse
composer format
```

Please include focused tests for behavioral changes. See the [changelog](CHANGELOG.md) and [license](LICENSE.md).

## Credits

- [Ahmad Rivaldi](https://github.com/ahmdrv)
