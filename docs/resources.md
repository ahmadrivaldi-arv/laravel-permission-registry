# Permission resources

A `PermissionResource` describes an authorization domain. It is not an Eloquent model and does not need a table, model class, policy, route, controller, or UI.

Good resource keys include `products`, `reports`, `settings`, `imports`, and `dashboards`.

## Permission naming

The package computes every permission name as:

```text
{resource_key}.{action_key}
```

Examples:

```text
products.view_any
products.update
products.publish
users.assign_roles
```

Resource, group, action, and exclusion keys must match:

```text
^[a-z][a-z0-9_]*$
```

Keys therefore:

- start with a lowercase ASCII letter;
- contain only lowercase letters, numbers, and underscores;
- cannot contain dots;
- should normally use plural resource domains.

The full permission name is never repeated manually in its own resource definition.

## Complete example

```php
<?php

declare(strict_types=1);

namespace App\Authorization\Permissions;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionRecommendation;

final class ProductPermissionResource extends PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::CRUD;

    public static function key(): string
    {
        return 'products';
    }

    public static function label(): string
    {
        return 'Products';
    }

    public static function group(): string
    {
        return 'catalog';
    }

    public static function description(): ?string
    {
        return 'Product catalog management';
    }

    protected static function actions(): array
    {
        return [
            PermissionAction::make('publish')
                ->label('Publish product')
                ->description('Make a product visible to customers.')
                ->risk(RiskLevel::HIGH)
                ->directGrantable()
                ->recommend(
                    PermissionRecommendation::make('reports.view_any')
                        ->reason('Useful for reviewing publication history.'),
                ),

            PermissionAction::make('delete')
                ->risk(RiskLevel::CRITICAL),
        ];
    }

}
```

The public definition-building method is final. Subclasses cannot skip merging, normalization, or validation.

## Presets

```php
protected static PermissionPreset $preset = PermissionPreset::CRUD;
```

| Preset | Ordered actions |
| --- | --- |
| `PermissionPreset::CRUD` | `view_any`, `view`, `create`, `update`, `delete` |
| `PermissionPreset::READ_ONLY` | `view_any`, `view` |
| `PermissionPreset::NONE` | none |

The base preset is CRUD, so a normal CRUD resource does not need to redeclare the property or implement `actions()`.

```php
final class ProductPermissionResource extends PermissionResource
{
    public static function key(): string { return 'products'; }
    public static function label(): string { return 'Products'; }
    public static function group(): string { return 'catalog'; }
}
```

Use `update`, not `edit`: permissions describe capabilities, not screens.

## Standard metadata defaults

| Action | Label | Risk | Direct grantable |
| --- | --- | --- | --- |
| `view_any` | View Any | Low | No |
| `view` | View | Low | No |
| `create` | Create | Medium | No |
| `update` | Update | Medium | No |
| `delete` | Delete | High | No |

Custom actions default to a humanized key label, medium risk, no description, no recommendations, and not directly grantable.

## Adding custom actions

Custom actions are appended after preset actions in declaration order:

```php
protected static function actions(): array
{
    return [
        PermissionAction::make('publish'),
        PermissionAction::make('archive'),
    ];
}
```

This adds `products.publish` and `products.archive` without repeating CRUD actions.

## Overriding standard actions

Using a preset action key overrides only explicitly supplied metadata:

```php
PermissionAction::make('delete')
    ->description('Permanently delete a product and its media.')
    ->risk(RiskLevel::CRITICAL);
```

The standard label remains `Delete`, and direct-grant eligibility remains false.

All builder methods return a new immutable value object. Chain the returned value as shown rather than expecting in-place mutation.

## Excluding actions

Exclusions run after preset/custom merging:

```php
protected static function exceptActions(): array
{
    return ['delete'];
}
```

Unknown exclusions fail validation. This catches likely typos such as `publsih` instead of silently doing nothing.

## Metadata reference

### Resource metadata

| Field | Source | Required |
| --- | --- | --- |
| Resource key | `key()` | Yes |
| Label | `label()` | Yes, non-empty |
| Description | `description()` | No |
| Group key | `group()` | Yes |
| Preset | static `$preset` | Defaults to CRUD |
| Ordered actions | preset, `actions()`, `exceptActions()` | Normalized by package |

### Action metadata

| Field | Builder | Behavior |
| --- | --- | --- |
| Action key | `PermissionAction::make('publish')` | Required and validated |
| Full name | Computed | `{resource}.{action}` |
| Label | `label('Publish product')` | Humanized key by default |
| Description | `description('...')` | Optional |
| Risk | `risk(RiskLevel::HIGH)` | Preset-specific or medium for custom actions |
| Direct eligibility | `directGrantable()` | False by default |
| Recommendation | `recommend(...)` | Advisory metadata only |

Group and risk metadata do not enforce authorization. They are provided for inspection, audit tools, filtering, and possible separate UI consumers.

## Merge order

The normalized definition is built in this exact order:

1. Load ordered preset actions.
2. Merge custom actions by action key.
3. Preserve unspecified preset metadata on overrides.
4. Append genuinely custom actions in declaration order.
5. Apply `exceptActions()`.
6. Normalize names and defaults.
7. Validate the resource.
8. Validate cross-resource recommendations after every resource is assembled.
