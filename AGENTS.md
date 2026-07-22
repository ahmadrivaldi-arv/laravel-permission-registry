# AGENTS.md

This file applies to the entire repository. Follow more specific instructions from a nested `AGENTS.md` if one is added later.

## Repository purpose

This repository contains `ahmdrv/laravel-permission-registry`, a headless, code-first Laravel permission registry built on `spatie/laravel-permission`.

The package defines, discovers, validates, inspects, and synchronizes permission names. Spatie remains responsible for persistence and effective authorization, while Laravel Gate and policies remain responsible for authorization decisions.

The configured package metadata is authoritative:

- Composer package: `ahmdrv/laravel-permission-registry`
- Root namespace: `Ahmdrv\PermissionRegistry`
- Configuration file: `config/permission-registry.php`
- Supported PHP: `^8.3`
- Supported Laravel: `^10 || ^11 || ^12 || ^13`
- Supported Spatie Permission: `^6.10`

Do not rerun a package-skeleton configurator or rename the package, vendor, namespace, author, repository, configuration key, or command prefix.

## Before changing code

1. Read this file, `README.md`, the relevant guide under `docs/`, `composer.json`, and affected tests.
2. Inspect `git status --short --branch` and preserve unrelated user changes.
3. Review the affected public contracts and neighboring implementations before editing.
4. Work on a dedicated task branch for substantive changes. Never commit, push, merge, tag, publish, or create a release unless the user explicitly requests that action.
5. Do not edit the ignored `composer.lock` as a deliverable. It may be refreshed locally when required for validation.

Use `rg` or `rg --files` for repository searches. Use `apply_patch` for manual file edits.

## Non-negotiable architecture

### Independent capabilities

Every permission is an independent capability named `{resource_key}.{action_key}`. Keys use lowercase snake case and cannot contain dots.

Never add:

- permission dependencies or `dependsOn`;
- implied or inherited permissions;
- automatic parent grants;
- negative permissions or direct deny;
- automatic grants caused by another selected permission.

For example, `users.assign_roles` must not imply, require, recommend by default, or grant `roles.view_any`.

### Recommendations are metadata only

Recommendations are immutable, directional advisory metadata. They may have a human-readable reason.

They must never:

- grant, revoke, deny, require, or imply access;
- preselect a permission;
- expand role or user synchronization input;
- create permission relationships or extra database rows;
- alter Gate, policy, Blade, Livewire, or Spatie effective-access behavior.

Validate recommendation targets only after the complete registry is assembled. Missing targets, self-references, and duplicates are errors. Cycles are warnings, not errors, and must not trigger dependency resolution or topological sorting.

### Resources and presets

`PermissionResource` represents an authorization domain, not an Eloquent model. Do not add model mapping, automatic policy mapping, controllers, routes, views, Livewire components, or frontend assets.

The supported presets are:

- `CRUD`: `view_any`, `view`, `create`, `update`, `delete`
- `READ_ONLY`: `view_any`, `view`
- `NONE`: no standard actions

Custom actions are additive. A custom action matching a preset action overrides only explicitly supplied metadata. Apply `exceptActions()` last. Keep the public definition builder final and all normalized ordering deterministic.

### Registry and persistence safety

The code registry is the source of truth for managed permission names. Validate the complete registry before database writes.

Synchronization is additive and idempotent by default. Recommendations never affect synchronization. Pruning must remain conservative:

- only `{currently_registered_resource_key}.{valid_action_key}` managed orphans are candidates;
- never prune unmanaged or unrelated permissions;
- never infer ownership for an entirely removed resource;
- display exact candidates and require confirmation unless `--force` is explicit.

Do not add a package-owned roles, permissions, users, manifest, or registry table. Use Spatie's models and migrations.

### Management security

Public mutating managers require an explicit `$actor`. Do not resolve `auth()->user()` inside domain services and do not introduce boolean authorization bypasses.

The default `ManagementAuthorizer` uses Laravel Gate. Trusted console, deployment, or seeding usage must bind an explicit alternative authorizer with a narrowly scoped trusted principal.

All permission inputs to management services must be registered in code and guard-compatible before mutation. Role grants are not restricted by `directGrantable`.

Direct user permissions are disabled globally by default. A direct grant requires both:

1. `direct_permissions.enabled` to be true; and
2. the action definition to be explicitly marked `directGrantable`.

Revoking a direct permission must not remove access inherited through a role. Do not add explicit deny, expiry, precedence, or approval semantics.

Use transactions for multi-step mutations where supported, clear Spatie's permission cache after successful changes, and dispatch focused events only after successful non-no-op changes. Authorization or validation failures must leave the database unchanged.

## Code conventions

- Use English for code, PHPDoc, exceptions, command output, tests, and documentation.
- Add `declare(strict_types=1);` to every PHP source, configuration, stub, and test file.
- Follow PSR-12 and the repository's Pint configuration.
- Use precise parameter and return types.
- Add PHPDoc only when native types cannot express useful information, especially iterable element types and extension-hook arrays.
- Prefer immutable `readonly` definition/value objects and backed enums where appropriate.
- Prefer small cohesive services and constructor injection through contracts.
- Keep output and filesystem discovery deterministic regardless of enumeration order.
- Use dedicated actionable domain exceptions for expected failures.
- Do not add a facade unless it provides clear, tested value.
- Avoid new dependencies unless the task requires them; declare direct dependencies for framework components used by production code.

## Important locations

- `src/Resources`: resource base class and preset normalization
- `src/ValueObjects`: developer-facing action and recommendation builders
- `src/Definitions`: normalized immutable registry output
- `src/Registry`: registration, discovery, validation, lookup, and grouping
- `src/Services`: synchronization and role/user management services
- `src/Commands`: `rbac:*` Artisan commands
- `src/Contracts`: container-facing public contracts
- `src/Authorization`: management authorization implementations
- `config/permission-registry.php`: focused package configuration
- `tests`: Pest and Testbench behavioral, command, integration, management, and architecture tests
- `docs`: detailed public documentation

Register configuration, commands, contracts, and services through `PermissionRegistryServiceProvider`. Never synchronize the database automatically during provider boot or web requests.

## Laravel compatibility

Preserve compatibility with Laravel 10, 11, 12, and 13 unless the user explicitly approves a breaking release.

The normal development dependencies use the modern Pest 4, Collision 8, Larastan 3, and Testbench 9-11 toolchain. Laravel 10 requires the isolated CI lane in `.github/workflows/run-tests.yml`, which substitutes Pest 2, Collision 7, and Testbench 8 and pins Spatie Permission 6.10.1.

Do not downgrade the normal development toolchain merely to test Laravel 10. Keep the Laravel 11-13 matrix intact when modifying the Laravel 10 lane. Pest 4 architecture tests remain covered by the modern matrix; the Laravel 10 lane runs the behavioral test files separately because of Pest 2 compatibility.

When changing dependency constraints:

1. verify a fresh Laravel 10 resolution with Spatie 6.10.1;
2. verify the current modern environment;
3. update the compatibility workflow and requirements documentation together;
4. do not manually edit the lockfile into an inconsistent state.

## Testing expectations

Use the existing Pest and Orchestra Testbench stack. Do not replace the test framework. Tests should assert public behavior rather than private implementation details.

Use the isolated SQLite test database and Spatie's test/published migrations through Testbench. Do not duplicate Spatie's production schema in this package.

Add or update tests for every behavior change. Security-sensitive changes should cover success, denial before mutation, guard mismatch, cache invalidation, events, idempotency, and recommendation non-interference as applicable.

Run the relevant focused test first, then all available quality gates before handing off a code change:

```bash
composer validate --strict
composer test
composer analyse
composer format
git diff --check
```

If formatting changes files, rerun tests and static analysis. Optional coverage is available through:

```bash
composer test-coverage
```

Do not claim a check passed unless it ran successfully. If the environment blocks a check, report the exact command and blocker.

PHPStan paths must point only to existing repository paths. The package intentionally has no production `database/` directory.

## Command behavior

All package commands use the `rbac:` prefix:

- `rbac:make-resource`
- `rbac:validate`
- `rbac:list`
- `rbac:diff`
- `rbac:sync`

Expected domain failures should produce concise errors and non-zero exit codes. Validation and diff are read-only. Sync is additive by default, supports dry runs, and must clear Spatie's cache at the correct time after successful writes.

The generator must use the package stub, validate class/key/group/preset input, respect `--force`, and explain explicit config registration when discovery is disabled. Do not rewrite consumer PHP configuration automatically.

## Documentation and release hygiene

Update documentation whenever public behavior, installation, configuration, commands, compatibility, or security semantics change. Keep these synchronized:

- `README.md` for the primary overview and links;
- `docs/README.md` for the documentation index;
- the relevant focused file under `docs/`;
- `CHANGELOG.md` under `Unreleased`.

Examples must use the actual `Ahmdrv\PermissionRegistry` namespace and reinforce that recommendations never grant or preselect permissions. Clearly distinguish global capabilities from record-level policy checks and presentation-only `@can` visibility.

Before a release, review the final diff for stale skeleton artifacts, unsafe pruning, permission implication, direct-grant bypasses, guard bugs, missing strict types, stale version requirements, and unrelated changes. Releasing requires an explicit user request and normally includes a versioned changelog entry, commit, push, merge, tag, and GitHub/Packagist release verification as separately authorized steps.
