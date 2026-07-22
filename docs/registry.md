# Registry registration, discovery, and queries

The container binds `Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry` as a singleton. The default implementation assembles and validates resources lazily on first use.

## Constructor injection

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;

final class PermissionCatalog
{
    public function __construct(private PermissionRegistry $registry) {}

    public function all(): array
    {
        return $this->registry->permissions();
    }
}
```

No facade is provided. Constructor injection keeps dependencies visible and makes consumers straightforward to test.

## Registration sources

The registry combines three sources:

1. classes registered programmatically;
2. classes listed in `permission-registry.resources`;
3. classes found by configured discovery.

Duplicate class registrations are de-duplicated before definitions are built. Duplicate resource keys and permission names across different classes are validation errors.

### Register one resource

```php
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use App\Authorization\Permissions\ProductPermissionResource;

public function boot(PermissionRegistry $registry): void
{
    $registry->register(ProductPermissionResource::class);
}
```

### Register many resources

```php
$registry->registerMany([
    ProductPermissionResource::class,
    ReportPermissionResource::class,
]);
```

Programmatic registration should happen during application/provider boot before another service first queries the singleton.

## Deterministic ordering

Resource classes are normalized independently of filesystem enumeration order. Final resources are ordered by:

1. group key;
2. resource key.

Actions retain preset order followed by custom declaration order, with exclusions removed. Flattened permissions follow the normalized resource and action order. Group maps are sorted by group key.

This makes CLI output and deployment diffs stable.

## Public API

```php
$registry->validate();

$resources = $registry->resources();
$permissions = $registry->permissions();
$recommendations = $registry->recommendations();

$resourcesByGroup = $registry->resourcesByGroup();
$permissionsByGroup = $registry->permissionsByGroup();

$products = $registry->findResource('products');
$publish = $registry->findPermission('products.publish');
$known = $registry->hasPermission('products.publish');
$warnings = $registry->warnings();
```

| Method | Return value |
| --- | --- |
| `register($class)` | Registry instance for chaining |
| `registerMany($classes)` | Registry instance for chaining |
| `resources()` | Ordered list of `ResourceDefinition` objects |
| `permissions()` | Ordered flattened list of `PermissionDefinition` objects |
| `recommendations()` | Flattened list of `RecommendationDefinition` objects |
| `resourcesByGroup()` | Group-keyed resource lists |
| `permissionsByGroup()` | Group-keyed permission lists |
| `findResource($key)` | Definition or null |
| `findPermission($name)` | Definition or null |
| `hasPermission($name)` | Boolean |
| `warnings()` | Non-fatal validation warnings |
| `validate()` | Completes successfully or throws `RegistryValidationException` |

## Normalized definitions

### `ResourceDefinition`

| Property | Type | Meaning |
| --- | --- | --- |
| `key` | string | Canonical resource key |
| `label` | string | Human-readable resource label |
| `description` | nullable string | Optional explanation |
| `groupKey` | string | Organization group |
| `preset` | `PermissionPreset` | Selected preset |
| `actions` | list of `PermissionDefinition` | Ordered normalized actions |
| `resourceClass` | string | Source class name |

### `PermissionDefinition`

| Property | Type | Meaning |
| --- | --- | --- |
| `actionKey` | string | Action portion of the name |
| `name` | string | Full `{resource}.{action}` permission |
| `label` | string | Human-readable action label |
| `description` | nullable string | Optional explanation |
| `risk` | `RiskLevel` | Low, medium, high, or critical |
| `directGrantable` | bool | Per-action direct grant eligibility |
| `recommendations` | list | Advisory recommendation definitions |
| `resourceKey` | string | Owning resource key |
| `resourceLabel` | string | Owning resource label |
| `groupKey` | string | Owning group |

### `RecommendationDefinition`

| Property | Type |
| --- | --- |
| `sourcePermission` | string |
| `targetPermission` | string |
| `reason` | nullable string |

The normalized objects are readonly value objects. Consumers should treat registry output as immutable metadata.

## Validation failures

The complete build detects:

- missing registered classes;
- classes that do not extend `PermissionResource`;
- abstract resources;
- invalid resource, group, or action keys;
- empty resource/action labels;
- malformed action values;
- duplicate resource keys;
- duplicate permission names;
- duplicate custom actions;
- unknown exclusions;
- missing, duplicate, malformed, or self-referencing recommendations;
- unusable discovery configuration.

Recommendation cycles are returned from `warnings()` instead of failing the build.

## No registry cache

Version 1 has no compiled registry cache. The singleton normalizes definitions once per application lifecycle. It does not write cache files, database manifests, or ownership metadata.
