# Configuration

Publish the configuration with:

```bash
php artisan vendor:publish --tag="laravel-permission-registry-config"
```

The complete default configuration is:

```php
<?php

declare(strict_types=1);

return [
    'guard' => null,

    'resources' => [
        // App\Authorization\Permissions\ProductPermissionResource::class,
    ],

    'discovery' => [
        'enabled' => true,
        'paths' => [app_path('Authorization/Permissions')],
        'namespace' => 'App\\Authorization\\Permissions',
    ],

    'direct_permissions' => [
        'enabled' => false,
    ],

    'management_abilities' => [
        'create_role' => 'roles.create',
        'update_role_permissions' => 'roles.update',
        'delete_role' => 'roles.delete',
        'assign_user_roles' => 'users.assign_roles',
        'manage_direct_permissions' => 'users.manage_direct_permissions',
    ],
];
```

## Guard

```php
'guard' => null,
```

When null, the package uses `auth.defaults.guard`. Commands can override it with `--guard`. `RoleManager::create()` and `RoleManager::find()` can also receive a guard argument.

```bash
php artisan rbac:diff --guard=admin
php artisan rbac:sync --guard=admin
```

Registry definitions are guard-agnostic. The guard is resolved only when reading or mutating Spatie persistence and assignments. A permission row for `web` is distinct from a row with the same name for `admin`.

For management services without a guard argument, set `permission-registry.guard` to the intended guard or ensure `auth.defaults.guard` is correct. Models, roles, and persisted permissions must all support that guard.

## Explicit resources

```php
'resources' => [
    App\Authorization\Permissions\ProductPermissionResource::class,
    App\Authorization\Permissions\ReportPermissionResource::class,
],
```

Explicit registration always works, including when discovery is disabled. Use it when:

- resource classes live in several modules;
- the application wants an audited allowlist;
- deployment artifacts do not support scanning;
- class names do not follow the discovery filename convention.

The registry rejects missing classes, abstract classes, and classes that do not extend `PermissionResource`.

## Discovery

```php
'discovery' => [
    'enabled' => true,
    'paths' => [app_path('Authorization/Permissions')],
    'namespace' => 'App\\Authorization\\Permissions',
],
```

Discovery recursively scans each configured path in deterministic filename order. A discoverable file should:

- end in `PermissionResource.php`;
- declare the class implied by the path and configured namespace;
- extend `Ahmdrv\PermissionRegistry\Resources\PermissionResource`;
- be concrete.

For example:

```text
Path:      app/Authorization/Permissions/Catalog/ProductPermissionResource.php
Namespace: App\Authorization\Permissions
Class:     App\Authorization\Permissions\Catalog\ProductPermissionResource
```

Unrelated PHP files are skipped and not loaded. Missing paths, unreadable directories, empty namespaces, and namespace/path mismatches fail validation with actionable errors.

Disable discovery for explicit-only registration:

```php
'discovery' => [
    'enabled' => false,
    'paths' => [app_path('Authorization/Permissions')],
    'namespace' => 'App\\Authorization\\Permissions',
],
```

When `rbac:make-resource` runs with discovery disabled, it prints the exact generated class that must be added to `resources`. It never rewrites the application's PHP config automatically.

## Direct permissions

```php
'direct_permissions' => [
    'enabled' => false,
],
```

Direct user mutations require this global flag and per-action `directGrantable()` metadata. The global flag does not make every permission directly grantable. Role permissions are not restricted by this setting.

Recommended default:

```php
'enabled' => env('RBAC_DIRECT_PERMISSIONS', false),
```

Keep it disabled unless the application has a documented direct-grant use case.

## Management abilities

Every public mutation manager accepts an actor and delegates to `ManagementAuthorizer`. The default implementation calls Laravel Gate with these configured abilities:

| Configuration key | Default ability | Protected operation |
| --- | --- | --- |
| `create_role` | `roles.create` | Create or find a role through `RoleManager::create()` |
| `update_role_permissions` | `roles.update` | Grant, revoke, or synchronize role permissions |
| `delete_role` | `roles.delete` | Delete a role |
| `assign_user_roles` | `users.assign_roles` | Assign, remove, or synchronize user roles |
| `manage_direct_permissions` | `users.manage_direct_permissions` | Grant, revoke, or synchronize direct permissions |

The package does not define, imply, or grant these abilities. Define them as resources or other application permissions and grant them through the application's normal provisioning process.

Abilities may be renamed without changing manager code:

```php
'management_abilities' => [
    'create_role' => 'security.roles.create',
    'update_role_permissions' => 'security.roles.update',
    'delete_role' => 'security.roles.delete',
    'assign_user_roles' => 'security.users.assign_roles',
    'manage_direct_permissions' => 'security.users.manage_direct_permissions',
],
```

See [Management authorization](management.md#management-authorization) for a custom authorizer binding.
