# Changelog

All notable changes to `laravel-permission-registry` will be documented in this file.

## Laravel Permission Registry v1.0.1 - 2026-07-22

### Laravel 10 compatibility

- Add Laravel 10 support while preserving Laravel 11, 12, and 13 support.
- Lower the `spatie/laravel-permission` requirement from 6.25 to 6.10 within major version 6.
- Add a dedicated Laravel 10, Testbench 8, Pest 2, and Spatie Permission 6.10.1 compatibility job.
- Keep the existing modern Laravel test matrix unchanged.

Laravel 10 applications running PHP 8.3 or newer and Spatie Permission 6.10.x can now install normally:

```bash
composer require ahmdrv/laravel-permission-registry

```
### Documentation

- Add a complete guide for seeding registered permissions, roles, and user-role assignments through the package APIs.
- Explain the explicit trusted-actor authorization pattern for initial database seeders.
- Add repository-wide AI agent guidance covering architecture, compatibility, security, testing, and release practices.

### Compatibility

- PHP 8.3 or newer
- Laravel 10, 11, 12, or 13
- `spatie/laravel-permission` 6.10 or newer within version 6

### Validation

- All 37 GitHub compatibility jobs passed across Laravel 10–13, PHP 8.3–8.5, Ubuntu, and Windows.
- PHPStan passed with no errors.
- Pint passed.
- Composer strict validation passed.
- Local suite: 36 tests and 139 assertions passed.

This release does not change the public API or authorization semantics. Recommendations remain advisory metadata only and never affect grants, persistence, or effective access.

## 1.0.1 - 2026-07-22

- Add Laravel 10 support and expand compatibility to `spatie/laravel-permission` 6.10 and newer within version 6.
- Document database seeding through the registry and secured management-service APIs.

## v1.0.0 - 2026-07-22

### Laravel Permission Registry v1.0.0

The first production-ready release of a headless, code-first permission resource registry built on [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

#### Highlights

- Define authorization domains as strict PHP resource classes.
- Expand CRUD, read-only, or empty presets without redeclaring standard actions.
- Add custom actions, selectively override metadata, and exclude preset actions.
- Discover, validate, inspect, diff, and synchronize permissions deterministically.
- Manage role permissions, user roles, and opt-in direct user permissions through secured services.
- Inspect effective access and distinguish direct permissions from role-derived permissions.
- Generate resources and operate the registry with the `rbac:*` Artisan command suite.
- Use comprehensive documentation covering installation, configuration, commands, authorization, operations, and every public feature.

#### Safety

- Recommendations are advisory metadata only. They never grant, imply, persist, require, or preselect permissions.
- Direct user permissions are disabled by default and require explicit per-permission eligibility.
- Permission synchronization is additive by default.
- Pruning only removes proven managed orphans and never unrelated permissions.
- Security-sensitive management operations require an explicitly supplied actor and authorization boundary.

#### Requirements

- PHP 8.3 or newer
- Laravel 11, 12, or 13
- `spatie/laravel-permission` 6.25 or newer within version 6

#### Installation

```bash
composer require ahmdrv/laravel-permission-registry


```
See the [README](https://github.com/ahmadrivaldi-arv/laravel-permission-registry#readme) and [documentation index](https://github.com/ahmadrivaldi-arv/laravel-permission-registry/blob/main/docs/README.md) for setup and usage.

## 1.0.0 - 2026-07-22

- Add the initial production-ready code-first permission resource registry.
- Add CRUD, read-only, and none presets with custom metadata overrides and exclusions.
- Add validated advisory recommendations with non-binding cycle warnings.
- Add deterministic explicit registration, discovery, inspection, diff, and synchronization.
- Add secured role, user-role, direct-permission, and access-inspection services.
- Add the `rbac:make-resource`, `rbac:validate`, `rbac:list`, `rbac:diff`, and `rbac:sync` commands.
- Add comprehensive installation, configuration, feature, command, management, authorization, troubleshooting, and operations documentation.
