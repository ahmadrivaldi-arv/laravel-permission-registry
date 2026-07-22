# Changelog

All notable changes to `laravel-permission-registry` will be documented in this file.

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
