# Laravel Permission Registry

[![Tests](https://github.com/ahmadrivaldi-arv/laravel-permission-registry/actions/workflows/run-tests.yml/badge.svg)](https://github.com/ahmadrivaldi-arv/laravel-permission-registry/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/ahmadrivaldi-arv/laravel-permission-registry/actions/workflows/phpstan.yml/badge.svg)](https://github.com/ahmadrivaldi-arv/laravel-permission-registry/actions/workflows/phpstan.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahmdrv/laravel-permission-registry.svg?style=flat-square)](https://packagist.org/packages/ahmdrv/laravel-permission-registry)

A headless, code-first registry for the permissions your Laravel application checks. You declare each feature's permissions in a PHP class next to your code; the package validates them, lists them, and synchronizes them into [spatie/laravel-permission](https://spatie.be/docs/laravel-permission). Spatie keeps storing roles and permissions, and Laravel Gate and policies keep making authorization decisions.

This package does not replace Laravel authorization. It does not provide a UI, couple resources to Eloquent models, infer access, or generate policies. A permission resource is an authorization domain such as products, dashboards, reports, settings, or imports.

## Why use it

Without a registry, permission names live as strings scattered across controllers, seeders, and Blade views. Nobody can easily answer "which permissions does this application have?", seeders drift from the code, and removed features leave stale rows behind.

This package makes the code the single source of truth:

- **One place per feature.** Every protected feature gets a permission resource that names its capabilities, such as `invoices.view_any`, `invoices.create`, or `invoices.send`.
- **Validated before it reaches the database.** Invalid keys, duplicates, and broken references fail `rbac:validate` in CI instead of in production.
- **Safe synchronization.** `rbac:sync` adds missing permissions and deletes nothing unless you ask it to prune, and then only rows it can prove it manages.
- **Guarded management.** Role and user services reject permission names that are not registered in code and authorize the acting user before any write.
- **Inspectable.** `rbac:list` and `rbac:diff` show exactly what exists in code and in the database.
- **Plain Laravel authorization.** You keep using `$this->authorize()`, policies, Gate, and `@can`. Nothing is implied or granted automatically.

## Quick start: protecting a new feature

Whenever you build a feature that should be restricted to certain users, give it a permission resource. Features that everyone may use need no resource.

Suppose you are adding invoicing, and only some staff may create, send, or void invoices.

**0. Prerequisite: Spatie Permission is installed and migrated.**

This package stores nothing itself; roles and permissions live in Spatie's tables. Follow [Spatie's installation guide](https://spatie.be/docs/laravel-permission/v6/installation-laravel) once per application (publish, migrate, add `HasRoles`), then:

```bash
composer require ahmdrv/laravel-permission-registry
```

**1. Generate a permission resource.**

```bash
php artisan rbac:make-resource Invoice --group=billing
```

This creates `app/Authorization/Permissions/InvoicePermissionResource.php` with the CRUD preset, which already provides `invoices.view_any`, `invoices.view`, `invoices.create`, `invoices.update`, and `invoices.delete`.

**2. Add the feature's own actions.**

```php
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;

protected static function actions(): array
{
    return [
        PermissionAction::make('send')->label('Send invoice to customer'),
        PermissionAction::make('void')->risk(RiskLevel::HIGH),
    ];
}
```

Declare only actions the feature really checks, and use `exceptActions()` to drop preset actions you do not need.

**3. Validate and synchronize.**

```bash
php artisan rbac:validate          # check definitions (a good CI step)
php artisan rbac:sync --dry-run    # preview what will be created
php artisan rbac:sync              # create the missing permission rows
```

Run `rbac:sync` during each deployment so a new feature's permissions exist before anyone is assigned them.

**4. Enforce the permissions in the feature.**

```php
// Controller or Livewire action: global capability
$this->authorize('invoices.create');

// Record-level rules (ownership, tenant, status) still belong in a policy
$this->authorize('void', $invoice);
```

```blade
@can('invoices.send')
    <button type="submit">Send invoice</button>
@endcan
```

`@can` only hides UI. Always authorize again on the server.

**5. Give the permissions to a role.**

```php
$role = $roleManager->create($actor, 'billing-clerk');
$roleManager->syncPermissions($actor, $role, ['invoices.view_any', 'invoices.create', 'invoices.send']);
$userRoleManager->assign($actor, $user, 'billing-clerk');
```

Managers require an explicit `$actor` and authorize it through Laravel Gate. For initial data, see [Seeding permissions, roles, and user assignments](docs/seeding.md).

That is the whole loop for every new feature: **resource → validate → sync → authorize → assign**. The [quick start guide](docs/quick-start.md) covers when a feature needs its own resource, a complete resource class, a policy example, and a checklist.

## Documentation

- [Documentation index](docs/README.md)
- [Quick start: protecting a new feature](docs/quick-start.md)
- [Installation and first setup](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Permission resources, presets, and metadata](docs/resources.md)
- [Advisory recommendations](docs/recommendations.md)
- [Registry registration, discovery, and queries](docs/registry.md)
- [Artisan command reference](docs/commands.md)
- [Role and user management services](docs/management.md)
- [Seeding permissions, roles, and user assignments](docs/seeding.md)
- [Laravel Gate, policies, Blade, and Livewire](docs/authorization.md)
- [Events, exceptions, and troubleshooting](docs/events-and-troubleshooting.md)
- [Deployment and operational safety](docs/operations.md)

## Requirements and installation

- PHP 8.3 or newer
- Laravel 10, 11, 12, or 13
- `spatie/laravel-permission` 6.10 or newer within version 6

**First, install and migrate Spatie Permission.** This package owns no database tables or migrations; roles and permissions live in Spatie's tables. Follow [Spatie's installation guide](https://spatie.be/docs/laravel-permission/v6/installation-laravel) once per application, including its migrations and the `HasRoles` trait on your user model.

**Then install this package:**

```bash
composer require ahmdrv/laravel-permission-registry
```

Optionally publish its focused configuration:

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

Risk levels are `LOW`, `MEDIUM`, `HIGH`, and `CRITICAL`. Preset and custom actions default to `directGrantable=true`. Declare `->directGrantable(false)` on any action, or override a preset action that way, to require assignment through roles. Groups are generic organization metadata and carry no navigation or authorization behavior.

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

Direct grants require `permission-registry.direct_permissions.enabled` to be true and the action's `directGrantable` metadata to be true. Preset and custom actions are eligible by default; any action can opt out with `->directGrantable(false)`. Eligibility never grants or preselects permissions. Role assignment ignores that metadata. Disabled direct mutations throw `DirectPermissionsDisabled` before writes, and ineligible grants throw `PermissionNotDirectGrantable`.

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

- [Ahmad Rivaldi](https://github.com/ahmadrivaldi-arv)
