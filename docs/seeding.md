# Seeding permissions, roles, and user assignments

Seeders can use the package's public services instead of writing directly to Spatie's models. This preserves registry validation, guard checks, transactions, cache invalidation, and package events.

The recommended order is:

1. validate the code registry;
2. synchronize registered permission rows;
3. create roles;
4. synchronize each role's explicit permission names;
5. optionally assign roles to users.

## Why seeders need an actor

Every mutating role or user manager receives an `$actor` as its first argument. The actor is the person or trusted system principal performing the security-sensitive operation. During a web request, the default authorizer checks that actor with Laravel Gate. For example, updating role permissions checks the configured `roles.update` ability.

An initial database seeder usually has no authenticated administrator. Do not pass `null` or add a boolean authorization bypass. Instead, install an explicit, narrowly scoped `ManagementAuthorizer` for the duration of the trusted seeder process and restore the default authorizer afterward.

## Complete role and permission seeder

Create `database/seeders/RolePermissionSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Definitions\PermissionDefinition;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Ahmdrv\PermissionRegistry\Services\RoleManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Seeder;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $defaultAuthorizer = app(ManagementAuthorizer::class);
        $actor = new \stdClass();

        app()->instance(
            ManagementAuthorizer::class,
            new class($actor) implements ManagementAuthorizer
            {
                public function __construct(
                    private readonly object $trustedActor,
                ) {}

                public function authorize(object $actor, string $ability): void
                {
                    if ($actor !== $this->trustedActor) {
                        throw new AuthorizationException(
                            "The actor cannot perform [{$ability}].",
                        );
                    }
                }
            },
        );

        try {
            $registry = app(PermissionRegistry::class);

            // Validate configured and discovered resource definitions.
            $registry->validate();

            // Add missing Spatie permission rows for the configured guard.
            app(PermissionSynchronizer::class)->sync();

            $roles = app(RoleManager::class);

            // Give administrators every permission currently in the registry.
            $administrator = $roles->create($actor, 'administrator');
            $allPermissions = array_map(
                static fn (PermissionDefinition $permission): string => $permission->name,
                $registry->permissions(),
            );
            $roles->syncPermissions($actor, $administrator, $allPermissions);

            // Give editors only this explicit registered permission set.
            $editor = $roles->create($actor, 'catalog-editor');
            $roles->syncPermissions($actor, $editor, [
                'products.view_any',
                'products.view',
                'products.create',
                'products.update',
                'products.publish',
            ]);

            // Create a read-only role.
            $viewer = $roles->create($actor, 'catalog-viewer');
            $roles->syncPermissions($actor, $viewer, [
                'products.view_any',
                'products.view',
            ]);
        } finally {
            app()->instance(ManagementAuthorizer::class, $defaultAuthorizer);
        }
    }
}
```

The example assumes that a registered resource defines the listed `products.*` permissions. Replace those names with permissions returned by `php artisan rbac:list` in your application.

`PermissionSynchronizer::sync()` is additive and idempotent. It creates missing permission rows but does not prune existing rows. `RoleManager::create()` uses find-or-create behavior, and `syncPermissions()` makes a role's permission set exactly match the explicitly supplied registered names.

Recommendations are metadata only. They never add permission rows or expand the permission names assigned to a role.

## Register and run the seeder

Call it from `database/seeders/DatabaseSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
    }
}
```

Run all application seeders:

```bash
php artisan db:seed
```

Or run only the RBAC seeder:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

## Assign a seeded role to a user

The user model must use Spatie's `HasRoles` trait. To assign roles during the same seeder, resolve `UserRoleManager` inside the `try` block after installing the temporary authorizer:

```php
use Ahmdrv\PermissionRegistry\Services\UserRoleManager;
use App\Models\User;

$user = User::query()
    ->where('email', 'admin@example.com')
    ->firstOrFail();

app(UserRoleManager::class)->sync(
    $actor,
    $user,
    ['administrator'],
);
```

Every requested role must already exist for the resolved guard. Synchronization replaces the user's role set for that guard with exactly the supplied role names.

## Using a real actor instead

If an authorized administrator already exists, use that model as the actor and keep the default Gate-based authorizer:

```php
$actor = User::query()->where('email', 'admin@example.com')->firstOrFail();
$role = app(RoleManager::class)->create($actor, 'catalog-editor');

app(RoleManager::class)->syncPermissions($actor, $role, [
    'products.view_any',
    'products.update',
]);
```

The actor must have the management abilities configured in `config/permission-registry.php`. Authorization is evaluated before any database mutation.

## Guard considerations

The permission synchronizer and management services resolve the package guard from `permission-registry.guard`, falling back to Laravel and Spatie defaults when it is `null`. Keep seeded permissions, roles, and user models on the same guard. For multiple guards, configure and seed each deployment context deliberately rather than mixing role or permission objects across guards.

