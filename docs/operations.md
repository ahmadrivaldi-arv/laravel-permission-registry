# Deployment and operational safety

## Recommended deployment sequence

Run validation in CI before deployment:

```bash
composer validate --strict
composer test
composer analyse
vendor/bin/pint --test
php artisan rbac:validate --no-interaction
```

Deploy application code, then preview and apply permissions for each intended guard:

```bash
php artisan rbac:diff --guard=web
php artisan rbac:sync --guard=web --dry-run
php artisan rbac:sync --guard=web --no-interaction
```

Repeat with another explicit guard only when the application intentionally uses the registry for it.

## Additive sync as the default

Default synchronization is safe for routine deployment because it:

- validates before writing;
- creates only missing registered names;
- never changes role or user assignments;
- never expands recommendations;
- never deletes permission rows;
- scopes database access to one resolved guard;
- uses a transaction for changes;
- clears Spatie's cache after success;
- is idempotent.

An application may deploy code and run the same sync repeatedly.

## Removing an action

Suppose `products.archive` is removed from code while `products` remains registered. Diff reports the database row as a managed orphan:

```bash
php artisan rbac:diff
```

Default sync leaves it untouched. To delete it:

```bash
php artisan rbac:sync --prune
```

The command displays the exact candidate and asks for confirmation.

Before pruning, consider whether application code, queued jobs, old deployments, roles, or external integrations still refer to the permission.

## Removing an entire resource

If the `products` resource itself is removed, `products.*` database rows become unmanaged rather than managed orphans. The package will not automatically delete them because it has no ownership manifest.

Handle full-resource removal explicitly:

1. deploy code that stops using the resource;
2. inspect role and user assignments;
3. remove assignments through an application migration/operation;
4. remove permission rows explicitly after compatibility windows close;
5. clear Spatie's cache.

This limitation prevents accidental deletion of similarly named third-party or application-owned permissions.

## Non-interactive pruning

Only use:

```bash
php artisan rbac:sync --prune --force --no-interaction
```

after CI or deployment automation has captured and reviewed the exact diff. `--force` bypasses the confirmation prompt but cannot broaden prune scope.

## Rollback behavior

Adding a permission row is normally safe to leave in place during a code rollback. Because default sync is additive, rolling code backward does not automatically delete new names.

If a rollback must remove a permission:

- inspect current assignments first;
- use the conservative prune flow only when its resource remains registered;
- otherwise use an explicit application-controlled migration;
- clear Spatie's cache after manual persistence changes.

## Cache behavior

Successful package mutations clear Spatie's permission cache after the transaction completes. Dry runs, validation, diff, failed mutations, and no-op synchronization do not clear it.

Direct SQL changes bypass package and Spatie model hooks. Operational scripts performing direct SQL are responsible for cache invalidation.

## Event behavior

Package events are post-success notifications. Use them for audit logging and integration, not as the transaction that enforces the permission change. An event listener failure occurs after the underlying mutation has committed, so critical audit guarantees may require an application-level transactional outbox.

## Guard checklist

Before production synchronization:

- choose the guard explicitly in config or the command;
- verify `auth.guards` and providers;
- verify custom Spatie role/permission models;
- ensure target user models support the guard;
- avoid creating duplicate names under an unintended guard;
- run diff separately for every guard.

## Security checklist

- Keep direct permissions disabled unless required.
- Mark only narrowly justified actions direct-grantable.
- Grant management abilities deliberately; the package never bootstraps them.
- Pass the real acting principal to every manager mutation.
- Bind trusted alternative authorizers only in narrowly scoped contexts.
- Authorize every server-side action even when UI controls are hidden.
- Keep record-level and tenant rules in policies.
- Treat recommendations as display-only metadata.
- Review every prune candidate.
- Never interpret unmanaged permissions as safe deletion candidates.

## Monitoring and audit data

Useful operational inputs include:

- JSON output from `rbac:list` as the intended catalog;
- JSON output from `rbac:diff` as deployment drift;
- `RegistrySynchronized` for created/deleted row counts;
- role/user/direct synchronization events for actor-attributed audit logs;
- `AccessInspector` for explaining effective access.

Do not expose sensitive user or authorization audit data in public logs.

## Version 1 boundaries

Version 1 does not include:

- a package ownership/manifest table;
- registry caching;
- automatic resource-removal pruning;
- automatic sync during application boot;
- background or remote synchronization;
- UI, routes, controllers, or Livewire components;
- dependency resolution or implied grants;
- negative, expiring, or approval-based permissions;
- package-level team/tenant abstractions beyond Spatie's behavior.
