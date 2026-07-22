# Documentation

Laravel Permission Registry is the code-owned catalog around `spatie/laravel-permission`. Resource classes define which permission names exist and attach metadata useful to commands, audits, and future presentation layers. Spatie remains responsible for permission persistence and effective authorization.

## Start here

1. [Install and perform the first setup](installation.md).
2. [Review the published configuration](configuration.md).
3. [Define permission resources](resources.md).
4. Validate and synchronize them with the [Artisan commands](commands.md).
5. Use the [management services](management.md) for roles and users.
6. Authorize application behavior through [Laravel Gate and policies](authorization.md).

## Complete guide

| Guide | Contents |
| --- | --- |
| [Installation](installation.md) | Requirements, Spatie prerequisites, user model setup, publishing configuration, first resource, first sync |
| [Configuration](configuration.md) | Guard resolution, explicit resources, discovery, direct permissions, management abilities |
| [Resources](resources.md) | Naming, presets, action merging, exclusions, labels, descriptions, risk, groups, direct eligibility |
| [Recommendations](recommendations.md) | Typed API, validation, cycles, and strict non-authorization semantics |
| [Registry](registry.md) | Registration, discovery rules, normalized definitions, lookup and grouping APIs |
| [Commands](commands.md) | Generator, validation, listing, diff, sync, JSON, dry-run, and prune behavior |
| [Management](management.md) | Roles, user roles, direct permissions, access inspection, guards, authorization boundary |
| [Authorization](authorization.md) | Gate, `$user->can()`, policies, Blade, Livewire, controllers, and independent capabilities |
| [Events and troubleshooting](events-and-troubleshooting.md) | Events, exceptions, common failures, and diagnosis |
| [Operations](operations.md) | CI checks, deployment sequence, idempotency, cache behavior, rollback, and pruning limitations |

## Architectural boundary

The package provides:

- deterministic code-first permission definitions;
- metadata and advisory recommendations;
- validation and discovery;
- database diff and synchronization against one resolved Spatie guard;
- secured role, user-role, and optional direct-permission services;
- effective-access inspection.

The package intentionally does not provide UI, routes, controllers, migrations, model mapping, policy generation, record-level rules, dependency resolution, implied permissions, negative permissions, tenancy abstractions, or broad ownership-based pruning.
