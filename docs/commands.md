# Artisan command reference

All package commands use the `rbac:` prefix and are registered through the package service provider.

## `rbac:make-resource`

Generate a resource in the first configured discovery path and namespace:

```bash
php artisan rbac:make-resource Product
php artisan rbac:make-resource Product --key=products --group=catalog --preset=crud
```

### Arguments and options

| Input | Default | Meaning |
| --- | --- | --- |
| `name` | required | Domain/class stem such as `Product` |
| `--key` | plural snake-case name | Explicit resource key |
| `--group` | `general` | Resource group key |
| `--preset` | `crud` | `crud`, `read-only`, or `none` |
| `--force` | false | Overwrite an existing generated file |

`Product` becomes `ProductPermissionResource`. The default key is generated with Laravel's pluralizer; use `--key` for non-English or irregular domain terms.

The generated class:

- enables strict types;
- extends `PermissionResource`;
- uses the selected preset;
- defines key, label, and group;
- does not redeclare standard actions;
- does not require an empty `actions()` hook.

When discovery is disabled, the command still creates the class and prints the exact FQCN to add to `permission-registry.resources`. It never rewrites PHP configuration.

Invalid class names, keys, groups, presets, or generator configuration return a failure exit code.

## `rbac:validate`

```bash
php artisan rbac:validate
```

Builds and validates the complete registry without reading or writing the permission database.

Successful output reports resource and permission counts. Fatal definition problems are printed as errors and return a non-zero exit code. Advisory recommendation cycles are printed as warnings and still return success.

Recommended CI step:

```bash
php artisan rbac:validate --no-interaction
```

## `rbac:list`

```bash
php artisan rbac:list
php artisan rbac:list --group=catalog
php artisan rbac:list --resource=products
php artisan rbac:list --risk=critical
php artisan rbac:list --with-recommendations
php artisan rbac:list --json
```

### Options

| Option | Meaning |
| --- | --- |
| `--group=<key>` | Include one group |
| `--resource=<key>` | Include one resource |
| `--risk=<level>` | Include `low`, `medium`, `high`, or `critical` |
| `--with-recommendations` | Add recommendation targets and reasons to the table |
| `--json` | Emit deterministic machine-readable JSON |

Table output includes group, resource, action, full permission, risk, and direct eligibility. JSON also includes label, description, and recommendations.

Examples:

```bash
php artisan rbac:list --group=catalog --risk=high
php artisan rbac:list --resource=products --with-recommendations
php artisan rbac:list --json > permissions.json
```

## `rbac:diff`

```bash
php artisan rbac:diff
php artisan rbac:diff --guard=web
php artisan rbac:diff --json
```

Diff validates the registry, resolves a guard, reads Spatie permission rows for only that guard, and reports four categories:

| Category | Meaning |
| --- | --- |
| Missing | Registered in code but absent from the database |
| Synchronized | Present in code and database |
| Managed orphan | Database-only permission under a currently registered resource key with a valid action shape |
| Unmanaged | Everything else, including removed resources and unrelated application permissions |

Example:

```text
Code registry: products.view, products.update
Database:      products.view, products.legacy, billing.refund

Missing:        products.update
Synchronized:   products.view
Managed orphan: products.legacy
Unmanaged:      billing.refund
```

Diff is read-only. Recommendations do not change any category.

## `rbac:sync`

```bash
php artisan rbac:sync
php artisan rbac:sync --guard=web
php artisan rbac:sync --dry-run
php artisan rbac:sync --prune
php artisan rbac:sync --prune --force
```

### Default behavior

Normal sync:

1. validates the complete registry;
2. resolves one Spatie guard;
3. computes the database diff;
4. opens a database transaction when changes exist;
5. creates missing permission names;
6. deletes nothing;
7. leaves unmanaged and managed-orphan rows untouched;
8. clears Spatie's permission cache after successful changes;
9. dispatches `RegistrySynchronized` after successful changes.

Running the same sync again is idempotent. A no-op sync does not clear cache or emit the package synchronization event.

Recommendations are validated but never materialized as database relationships or assignments.

### Dry run

```bash
php artisan rbac:sync --dry-run
```

Reports the intended counts without creating or deleting rows, clearing cache, or dispatching the synchronization event.

### Prune

```bash
php artisan rbac:sync --prune
```

Prune considers only managed orphans. It prints exact deletion candidates and asks for confirmation. Declining confirmation returns failure without writes.

For non-interactive deployments:

```bash
php artisan rbac:sync --prune --force --no-interaction
```

Use `--force` only after reviewing `rbac:diff` or a dry run. It bypasses confirmation, not the managed-orphan safety boundary.

The package never prunes:

- permissions outside currently registered resource keys;
- malformed names;
- third-party/application permissions in other namespaces;
- permissions from an entirely removed resource.

Version 1 has no ownership manifest, so it cannot prove that a removed resource's old permissions belong to this package.

## Programmatic diff and sync

Commands delegate to `PermissionSynchronizer`, which may also be injected:

```php
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;

$diff = $synchronizer->diff('web');
$result = $synchronizer->sync(guard: 'web', dryRun: true);
```

`PermissionDiff` exposes `guard`, `missing`, `synchronized`, `managedOrphans`, and `unmanaged`. `SynchronizationResult` exposes `guard`, `created`, `existing`, `deleted`, `unmanaged`, `untouched`, and `dryRun`.

Prune candidates supplied programmatically must be a subset of `PermissionDiff::$managedOrphans`. Anything else throws `UnsafePruneRequest` before mutation.
