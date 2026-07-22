# Events, exceptions, and troubleshooting

## Package events

Events are dispatched only after successful changes. Validation errors, authorization denial, failed transactions, dry runs, and idempotent no-ops do not emit the corresponding package synchronization event.

| Event | Dispatched after | Important properties |
| --- | --- | --- |
| `RegistrySynchronized` | Permission rows were created and/or safely pruned | `result` |
| `RolePermissionsSynchronized` | A role's permission set changed | `role`, `permissions`, `actor` |
| `UserRolesSynchronized` | A user's role set changed | `user`, `roles`, `actor` |
| `DirectPermissionGranted` | One direct permission was newly granted | `user`, `permission`, `actor` |
| `DirectPermissionRevoked` | One direct permission was removed | `user`, `permission`, `actor` |
| `DirectPermissionsSynchronized` | A user's direct permission set changed | `user`, `permissions`, `actor` |

Listen with Laravel's normal event system:

```php
use Ahmdrv\PermissionRegistry\Events\RolePermissionsSynchronized;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(RolePermissionsSynchronized::class, function (RolePermissionsSynchronized $event): void {
    Log::info('Role permissions synchronized', [
        'actor_type' => $event->actor::class,
        'role' => $event->role->name,
        'permissions' => $event->permissions,
    ]);
});
```

The package does not emit a role-created or role-deleted event. Use Eloquent/Spatie events if those lifecycle notifications are needed.

## Domain exceptions

| Exception | Meaning |
| --- | --- |
| `RegistryValidationException` | Complete registry validation failed; inspect its `errors` list |
| `InvalidResourceDefinition` | A resource/action key or value is malformed |
| `DuplicateDefinition` | A resource key or full permission is duplicated |
| `UnregisteredPermission` | A management input is absent from the code registry |
| `GuardMismatch` | Guard resolution failed or models/rows do not match the resolved guard |
| `DirectPermissionsDisabled` | A direct mutation was attempted while globally disabled |
| `PermissionNotDirectGrantable` | A direct grant/sync included an ineligible action |
| `UnsafePruneRequest` | Programmatic prune candidates exceeded the managed-orphan boundary |

Laravel's standard `Illuminate\Auth\Access\AuthorizationException` is used when the default management authorizer denies an actor.

Unexpected database and infrastructure exceptions are not hidden. Transactions roll back and Laravel retains useful debugging context.

## Troubleshooting

### Discovery path is not readable

Example:

```text
Discovery path [...] is not a readable directory. Create it or disable discovery.
```

Create the configured directory:

```bash
mkdir -p app/Authorization/Permissions
```

Or disable discovery and explicitly list resource classes.

### Discovery candidate does not declare the expected class

Confirm that directory nesting, namespace, filename, and class name agree:

```text
app/Authorization/Permissions/Catalog/ProductPermissionResource.php
App\Authorization\Permissions\Catalog\ProductPermissionResource
```

### A permission is registered but not synchronized for the guard

Run:

```bash
php artisan rbac:diff --guard=web
php artisan rbac:sync --guard=web
```

Then confirm the manager resolves the same guard. A `web` permission row cannot be assigned to an `admin` role.

### Direct permissions are disabled

Enable the global flag only when direct grants are intentional:

```php
'direct_permissions' => ['enabled' => true],
```

The action must also call `directGrantable()`.

### A direct permission is not eligible

Add eligibility to that exact action:

```php
PermissionAction::make('publish')->directGrantable();
```

Do not mark broad or destructive capabilities eligible merely to bypass role design.

### Management authorization is denied

Check:

1. the operation-to-ability mapping in `permission-registry.management_abilities`;
2. that the ability itself exists and is synchronized;
3. that the actor has it for the relevant guard;
4. that the consumer has not replaced `ManagementAuthorizer` unexpectedly.

The package never auto-grants management abilities.

### An exclusion fails as unknown

`exceptActions()` runs after preset and custom actions are merged. Correct the spelling, choose a preset that contains the action, or declare the custom action before excluding it.

### A recommendation target is missing

The target must be a fully qualified registered name such as `reports.view_any`, not only `view_any`. Register its resource explicitly, enable its discovery path, or remove the recommendation.

### Recommendation cycle warning

Cycles are informational because recommendations do not imply access. Review whether the metadata remains useful; no topological ordering or dependency resolution is needed.

### A removed resource permission is reported unmanaged

This is expected. Without persistent ownership metadata, the package cannot prove that a permission from an entirely removed resource is safe to delete. Review and remove it manually through an application-controlled migration or operational procedure.

### Permission checks appear stale

Package mutations clear Spatie's permission cache after success. If permissions were changed directly through SQL or another process, clear Spatie's cache using its documented cache-reset command or `PermissionRegistrar::forgetCachedPermissions()`.

### `rbac:sync --prune` does not delete a row

Only names under a currently registered resource key and shaped as exactly `{resource}.{valid_action}` are managed orphans. Everything else is intentionally protected.

## Diagnostic sequence

Use this order when diagnosing registry/deployment problems:

```bash
php artisan rbac:validate
php artisan rbac:list --json
php artisan rbac:diff --guard=web --json
php artisan rbac:sync --guard=web --dry-run
```

The first failing step normally identifies whether the problem is definition, discovery, guard configuration, persistence, or pruning scope.
