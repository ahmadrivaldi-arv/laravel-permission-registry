# Documentation

Laravel Permission Registry is the code-owned catalog around `spatie/laravel-permission`. Resource classes define which permission names exist and attach metadata useful to commands, audits, and future presentation layers. Spatie remains responsible for permission persistence and effective authorization.

## Start here

1. [Install and perform the first setup](installation.md).
2. Follow the [quick start](quick-start.md) to protect a new feature from resource to role assignment.
3. [Review the published configuration](configuration.md).
4. [Define permission resources](resources.md).
5. Validate and synchronize them with the [Artisan commands](commands.md).
6. Create initial access data with the [seeding guide](seeding.md).
7. Use the [management services](management.md) for roles and users.
8. Authorize application behavior through [Laravel Gate and policies](authorization.md).

## Complete guide

| Guide | Contents |
| --- | --- |
| [Quick start](quick-start.md) | When a feature needs a resource, the resource → validate → sync → authorize → assign workflow, a policy example, and a checklist |
| [Installation](installation.md) | Requirements, Spatie prerequisites, user model setup, publishing configuration, first resource, first sync |
| [Configuration](configuration.md) | Guard resolution, explicit resources, discovery, direct permissions, management abilities |
| [Resources](resources.md) | Naming, presets, action merging, exclusions, labels, descriptions, risk, groups, preset direct eligibility and explicit opt-outs |
| [Recommendations](recommendations.md) | Typed API, validation, cycles, and strict non-authorization semantics |
| [Registry](registry.md) | Registration, discovery rules, normalized definitions, lookup and grouping APIs |
| [Commands](commands.md) | Generator, validation, listing, diff, sync, JSON, dry-run, and prune behavior |
| [Management](management.md) | Roles, user roles, direct permissions, access inspection, guards, authorization boundary |
| [Seeding](seeding.md) | Registry synchronization, role creation, trusted seeder actors, and initial user-role assignments |
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
