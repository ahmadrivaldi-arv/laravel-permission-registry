# Quick start: protecting a new feature

This guide walks through the everyday workflow: you are building a new feature, some users may use it and others may not, so it needs access control. Step 1 is a one-time prerequisite; steps 2 to 7 repeat for every new feature.

## When does a feature need a permission resource?

Create a permission resource when a feature should be available to some users and not others. Features that every authenticated user may use need no resource.

A resource describes an **authorization domain**, not an Eloquent model:

| Situation | What to do |
| --- | --- |
| New area that some users must not access, such as invoicing | Create a new resource (`invoices`) |
| New action inside an existing area, such as exporting products | Add an action to the existing resource (`products.export`) |
| Area that is not tied to one model, such as dashboards, reports, or imports | Create a resource for that domain (`reports`, `imports`) |
| Feature everyone may use, such as editing your own profile | No resource; use policies or ordinary validation |
| Rule that depends on a specific record, such as "only the owner may edit" | Keep it in a policy, alongside the capability check |

## The workflow at a glance

```text
1. Prerequisite          Spatie Permission installed and migrated (once per application)
2. Generate a resource   php artisan rbac:make-resource Invoice --group=billing
3. Declare actions       edit actions() / exceptActions() in the resource
4. Validate              php artisan rbac:validate
5. Synchronize           php artisan rbac:sync --dry-run, then php artisan rbac:sync
6. Enforce               $this->authorize('invoices.create'), policies, @can
7. Assign                grant the permissions to roles
```

The example below adds invoicing. Only some staff may create, send, or void invoices.

## 1. Prerequisite: Spatie Permission is installed and migrated

This package owns no tables; roles, permissions, and assignments live in Spatie's tables. Before your first feature, follow [Spatie's installation guide](https://spatie.be/docs/laravel-permission/v6/installation-laravel) once per application, including its migrations and the `HasRoles` trait on your user model. Then install this package:

```bash
composer require ahmdrv/laravel-permission-registry
```

See [installation](installation.md) for this package's configuration and provider registration.

## 2. Generate the resource

```bash
php artisan rbac:make-resource Invoice --group=billing
```

The command creates `app/Authorization/Permissions/InvoicePermissionResource.php` (the first configured discovery path) and prints the class, key, group, preset, and next steps. Options:

- `--key=` sets the resource key when Laravel's English pluralizer is wrong for your domain;
- `--group=` groups related resources for listing; it has no authorization effect;
- `--preset=crud|read-only|none` chooses the standard actions (default `crud`).

With discovery enabled, the class is registered automatically. With discovery disabled, add the printed class name to `permission-registry.resources`.

## 3. Declare the feature's actions

The CRUD preset already provides `invoices.view_any`, `invoices.view`, `invoices.create`, `invoices.update`, and `invoices.delete`. Add the actions your feature checks beyond those, and remove preset actions it never uses:

```php
<?php

declare(strict_types=1);

namespace App\Authorization\Permissions;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;

final class InvoicePermissionResource extends PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::CRUD;

    public static function key(): string
    {
        return 'invoices';
    }

    public static function label(): string
    {
        return 'Invoices';
    }

    public static function group(): string
    {
        return 'billing';
    }

    protected static function actions(): array
    {
        return [
            PermissionAction::make('send')->label('Send invoice to customer'),
            PermissionAction::make('void')
                ->description('Cancel an issued invoice.')
                ->risk(RiskLevel::HIGH),
        ];
    }

    protected static function exceptActions(): array
    {
        return ['delete']; // invoices are voided, never deleted
    }
}
```

This resource defines exactly six independent permissions: `invoices.view_any`, `invoices.view`, `invoices.create`, `invoices.update`, `invoices.send`, and `invoices.void`. None of them implies another. See [resources](resources.md) for presets, metadata, and direct-grant eligibility.

## 4. Validate

```bash
php artisan rbac:validate
php artisan rbac:list --resource=invoices
```

`rbac:validate` reads no database and fails with a non-zero exit code on invalid keys, duplicates, or broken recommendation targets. Add it to CI so definition mistakes never reach production.

## 5. Synchronize the permission rows

```bash
php artisan rbac:sync --dry-run   # shows what would be created
php artisan rbac:sync             # creates the missing rows
```

Sync is additive and idempotent: it creates missing permissions and leaves every existing or unrelated row untouched. Run it during each deployment, before anyone is assigned the new permissions. See [operations](operations.md) for the deployment order and [commands](commands.md) for pruning.

## 6. Enforce the permissions in the feature

Check the global capability where the action happens:

```php
public function store(StoreInvoiceRequest $request): RedirectResponse
{
    $this->authorize('invoices.create');

    // ...
}
```

Keep record-specific rules in a policy and combine them with the capability:

```php
public function void(User $user, Invoice $invoice): bool
{
    return $user->can('invoices.void') && ! $invoice->isPaid();
}
```

```php
$this->authorize('void', $invoice);
```

Hide controls the user cannot use:

```blade
@can('invoices.send')
    <button type="submit">Send invoice</button>
@endcan
```

`@can` is presentation only. Every server-side action, including Livewire actions, must authorize again. See [authorization](authorization.md).

## 7. Assign the permissions

Grant the new permissions to roles through the management services:

```php
use Ahmdrv\PermissionRegistry\Services\RoleManager;
use Ahmdrv\PermissionRegistry\Services\UserRoleManager;

$role = $roleManager->create($actor, 'billing-clerk');
$roleManager->syncPermissions($actor, $role, [
    'invoices.view_any',
    'invoices.view',
    'invoices.create',
    'invoices.send',
]);

$userRoleManager->assign($actor, $user, 'billing-clerk');
```

`$actor` is the administrator performing the change; the default authorizer checks it with Laravel Gate. Permission names must be registered in code and synchronized for the guard, otherwise the call fails before any write. For initial data without an authenticated administrator, follow the [seeding guide](seeding.md).

## Checklist for every new feature

- [ ] The feature has a resource, or an action on an existing resource, for each capability it checks.
- [ ] `php artisan rbac:validate` passes.
- [ ] Deployment runs `php artisan rbac:sync`.
- [ ] Each controller or Livewire action authorizes its exact permission; record rules live in policies.
- [ ] UI visibility with `@can` matches the server-side checks.
- [ ] Roles that need the feature are granted its permissions explicitly.

## Common mistakes

- **Using a permission name that is not registered.** Gate simply returns false for it. Check with `php artisan rbac:list` and use the full `{resource}.{action}` name.
- **Expecting one permission to include another.** `invoices.void` does not grant `invoices.view`. Grant every permission a role needs.
- **Treating recommendations as grants.** Recommendations are advisory metadata and never grant, require, or preselect a permission. See [recommendations](recommendations.md).
- **Forgetting to sync after deploying.** Assigning an unsynchronized permission fails with a guard mismatch. Run `php artisan rbac:diff` to see what is missing.
