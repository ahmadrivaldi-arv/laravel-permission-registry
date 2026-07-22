# Advisory recommendations

Recommendations are typed metadata for capabilities that are commonly useful together. They are not dependencies.

## Defining a recommendation

```php
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionRecommendation;

PermissionAction::make('export')
    ->recommend(
        PermissionRecommendation::make('reports.view_any')
            ->reason('Useful when the user also needs to browse report history.'),
    );
```

The reason is optional:

```php
PermissionRecommendation::make('reports.view_any');
```

Add multiple recommendations with multiple calls:

```php
PermissionAction::make('publish')
    ->recommend(PermissionRecommendation::make('reports.view_any'))
    ->recommend(PermissionRecommendation::make('audit_logs.view_any'));
```

The API deliberately does not accept ambiguous unstructured recommendation arrays.

## Validation

Recommendation validation occurs only after the complete registry is assembled, so one resource may target a permission declared by another resource.

A target must:

- be a fully qualified `{resource}.{action}` name;
- match the canonical lowercase snake-case format;
- exist in the assembled code registry;
- differ from its source permission;
- occur only once for that source permission.

Missing targets, malformed names, self-references, and duplicates are errors. Recommendation cycles are warnings.

```text
reports.export -> audit_logs.view_any -> reports.export
```

The cycle does not fail validation because no traversal or dependency resolution occurs. `rbac:validate` reports it for maintainers, and the registry exposes it through `warnings()`.

## Authorization semantics

Recommendations never:

- grant or revoke permissions;
- imply a permission;
- require a target permission;
- deny access when a target is absent;
- alter role synchronization;
- alter direct-permission synchronization;
- add database assignments or recommendation relationships;
- affect Laravel Gate, policies, `$user->can()`, or Blade `@can`;
- affect Spatie's effective permission calculation;
- affect diff or prune categories;
- preselect a permission in a future UI.

For example, synchronizing only `products.publish` does not synchronize or assign its recommended `reports.view_any` permission to the role or user:

```php
$roleManager->syncPermissions($actor, $role, ['products.publish']);

// The role has products.publish only.
// reports.view_any remains unassigned.
```

The target permission may still have its own database row because every registered permission is synchronized by name. That row is not an assignment and does not provide access.

## Direction

Recommendation direction is intentional and not symmetric. If `products.publish` recommends `reports.view_any`, the registry does not add a reverse edge. Define the reverse recommendation explicitly only if it is independently useful.

`users.assign_roles` does not recommend `roles.view_any` by default. Authorizing the role-assignment workflow is enough for that workflow to load role options; it does not grant or imply general role browsing.

## Reading recommendations

```php
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;

final class RecommendationReporter
{
    public function __construct(private PermissionRegistry $registry) {}

    public function all(): array
    {
        return $this->registry->recommendations();
    }
}
```

Each `RecommendationDefinition` contains:

- `sourcePermission`;
- `targetPermission`;
- `reason`.

Use `rbac:list --with-recommendations` or `rbac:list --json` for command-line inspection.
