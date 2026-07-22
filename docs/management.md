# Role and user management

The package provides small application services around Spatie's role and permission models. They are optional: applications may continue using Spatie directly. The package services add registry validation, guard checks, transactions, cache invalidation, focused events, and an explicit authorization boundary.

All mutating methods receive the acting principal as their first argument. They never resolve `auth()->user()` internally.

## Prerequisites

Before assigning a registered permission:

1. the permission must exist in the code registry;
2. `rbac:sync` must have created its Spatie row for the selected guard;
3. the target model/role must use a compatible guard;
4. the actor must pass the configured management ability;
5. direct grants must additionally pass both direct-permission opt-ins.

Consumer user models must be Eloquent models using Spatie's `HasRoles` trait.

For initial application data, see [Seeding permissions, roles, and user assignments](seeding.md). The guide shows how to use these services from a trusted database seeder without weakening the authorization boundary.

## `RoleManager`

Inject the service:

```php
use Ahmdrv\PermissionRegistry\Services\RoleManager;

final class RoleService
{
    public function __construct(private RoleManager $roles) {}
}
```

### Create or find a role

```php
$role = $this->roles->create($actor, 'catalog-editor');
$adminRole = $this->roles->create($actor, 'administrator', 'admin');
```

`create()` uses Spatie's `findOrCreate` semantics and is authorized with the configured `create_role` ability.

### Find a role

```php
$role = $this->roles->find('catalog-editor');
$role = $this->roles->find('administrator', 'admin');
```

Finding is read-only and does not require an actor.

### Synchronize permissions

```php
$this->roles->syncPermissions($actor, $role, [
    'products.view_any',
    'products.view',
    'products.create',
    'products.update',
]);
```

Synchronization uses exactly the names passed. Recommendations never add, remove, or filter permission names.

### Grant or revoke one permission

```php
$this->roles->grant($actor, $role, 'products.publish');
$this->roles->revoke($actor, $role, 'products.delete');
```

Both operations produce the same normalized role-permission synchronization behavior and event. Idempotent no-ops emit no package event.

### Delete a role

```php
$deleted = $this->roles->delete($actor, $role);
```

The operation is authorized with `delete_role`, runs transactionally, and clears Spatie's cache after a successful deletion.

## `UserRoleManager`

```php
use Ahmdrv\PermissionRegistry\Services\UserRoleManager;

final class UserAccessService
{
    public function __construct(private UserRoleManager $userRoles) {}

    public function makeEditor(object $actor, object $user): void
    {
        $this->userRoles->assign($actor, $user, 'catalog-editor');
    }
}
```

### Assign one role

```php
$userRoleManager->assign($actor, $user, 'catalog-editor');
```

### Remove one role

```php
$userRoleManager->remove($actor, $user, 'catalog-editor');
```

### Synchronize all roles

```php
$userRoleManager->sync($actor, $user, [
    'catalog-editor',
    'report-viewer',
]);
```

Every requested role must already exist for the resolved guard. The actor is authorized with `assign_user_roles`. Successful changes dispatch `UserRolesSynchronized`; identical input is a no-op.

## `DirectPermissionManager`

Direct permissions are exceptional overrides, not the preferred access model.

### Enable the global feature

```php
// config/permission-registry.php
'direct_permissions' => [
    'enabled' => true,
],
```

### Mark individual actions eligible

```php
PermissionAction::make('publish')->directGrantable();
```

Both conditions are required. `directGrantable` does not restrict role assignments.

### Grant, revoke, or synchronize

```php
use Ahmdrv\PermissionRegistry\Services\DirectPermissionManager;

$directPermissions->grant($actor, $user, 'products.publish');
$directPermissions->revoke($actor, $user, 'products.publish');
$directPermissions->sync($actor, $user, [
    'products.publish',
]);
```

Rules:

- every name must exist in the code registry;
- every granted/synchronized name must be marked direct-grantable;
- every permission row must exist for the resolved guard;
- the target model must support the resolved guard;
- the actor must pass `manage_direct_permissions`;
- recommendations never expand the input;
- disabled mutations throw before database changes;
- no-op calls do not emit events.

Revoking a direct permission removes only the direct assignment. If a role also grants the same permission, `$user->can()` remains true through normal Spatie union semantics.

Version 1 does not implement direct deny, expiry, approval, or precedence rules.

## `AccessInspector`

Inspect why a user has each effective Spatie permission:

```php
use Ahmdrv\PermissionRegistry\Services\AccessInspector;

$access = $inspector->inspect($user);

foreach ($access as $permission) {
    echo $permission->permission;
    echo $permission->direct ? 'direct' : 'not direct';
    echo implode(', ', $permission->roles);
    echo $permission->registered ? 'registered' : 'outside registry';
}
```

Each readonly `EffectivePermission` contains:

| Property | Meaning |
| --- | --- |
| `permission` | Full Spatie permission name |
| `direct` | Whether the user has a direct assignment |
| `roles` | Sorted roles contributing that permission |
| `registered` | Whether the name exists in the code registry |

A permission may be both direct and role-derived. The inspector reports both sources. It does not change effective access.

## Management authorization

The default `GateManagementAuthorizer` executes:

```php
Gate::forUser($actor)->authorize($configuredAbility);
```

Denied authorization raises Laravel's standard `AuthorizationException` before mutation.

### Custom authorizer

Implement the contract when a different authorization mechanism is required:

```php
<?php

declare(strict_types=1);

namespace App\Authorization;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;

final class DeploymentManagementAuthorizer implements ManagementAuthorizer
{
    public function authorize(object $actor, string $ability): void
    {
        if (! $actor instanceof TrustedDeploymentPrincipal) {
            throw new AuthorizationException('This principal cannot manage RBAC state.');
        }
    }
}
```

Bind it explicitly:

```php
use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use App\Authorization\DeploymentManagementAuthorizer;

$this->app->bind(
    ManagementAuthorizer::class,
    DeploymentManagementAuthorizer::class,
);
```

Scope trusted bindings narrowly to the intended deployment/queue context. There is no undocumented boolean bypass.

## Transactions, cache, and events

Multi-step changes use the target model's database connection transaction. After a successful mutation, managers clear Spatie's permission cache and dispatch the matching package event. Failed validation, denied authorization, failed transactions, and no-op calls do not dispatch package synchronization events.
