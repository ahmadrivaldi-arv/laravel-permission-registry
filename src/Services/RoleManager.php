<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Services;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Events\RolePermissionsSynchronized;
use Ahmdrv\PermissionRegistry\Exceptions\GuardMismatch;
use Ahmdrv\PermissionRegistry\Support\GuardResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\PermissionRegistrar;

final readonly class RoleManager
{
    public function __construct(
        private ManagementAuthorizer $authorizer,
        private PermissionInputValidator $permissions,
        private GuardResolver $guards,
        private Repository $config,
        private PermissionRegistrar $cache,
        private Dispatcher $events,
    ) {}

    public function create(object $actor, string $name, ?string $guard = null): Role
    {
        $this->authorizer->authorize($actor, $this->ability('create_role'));
        $guard = $this->guards->resolve($guard);
        $class = $this->roleModel();

        /** @var Role $role */
        $role = (new $class)->getConnection()->transaction(fn (): Role => $class::findOrCreate($name, $guard));
        $this->cache->forgetCachedPermissions();

        return $role;
    }

    public function find(string $name, ?string $guard = null): Role
    {
        $class = $this->roleModel();

        return $class::findByName($name, $this->guards->resolve($guard));
    }

    public function delete(object $actor, Role $role): bool
    {
        $this->authorizer->authorize($actor, $this->ability('delete_role'));
        $model = $this->roleAsModel($role);
        $deleted = (bool) $model->getConnection()->transaction(fn (): ?bool => $model->delete());
        if ($deleted) {
            $this->cache->forgetCachedPermissions();
        }

        return $deleted;
    }

    /** @param iterable<string> $permissions */
    public function syncPermissions(object $actor, Role $role, iterable $permissions): Role
    {
        $this->authorizer->authorize($actor, $this->ability('update_role_permissions'));
        $model = $this->roleAsModel($role);
        $guard = (string) $model->getAttribute('guard_name');
        if ($guard === '') {
            throw new GuardMismatch('Role ['.(string) $model->getAttribute('name').'] has no guard_name.');
        }
        $names = $this->permissions->registeredForGuard($permissions, $guard);

        $current = $role->permissions()->get()->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->sort()->values()->all();
        if ($current === $names) {
            return $role;
        }

        $model->getConnection()->transaction(fn () => $role->syncPermissions($names));
        $this->cache->forgetCachedPermissions();
        $this->events->dispatch(new RolePermissionsSynchronized($role, $names, $actor));

        return $role;
    }

    public function grant(object $actor, Role $role, string $permission): Role
    {
        $current = $role->permissions()->get()->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->all();

        return $this->syncPermissions($actor, $role, [...$current, $permission]);
    }

    public function revoke(object $actor, Role $role, string $permission): Role
    {
        $this->permissions->registered([$permission]);
        $current = $role->permissions()->get()->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->reject(fn (string $name): bool => $name === $permission)->all();

        return $this->syncPermissions($actor, $role, $current);
    }

    private function ability(string $operation): string
    {
        $ability = $this->config->get("permission-registry.management_abilities.{$operation}");
        if (! is_string($ability) || $ability === '') {
            throw new \LogicException("Management ability [{$operation}] is not configured.");
        }

        return $ability;
    }

    /** @return class-string<Model&Role> */
    private function roleModel(): string
    {
        $class = $this->config->get('permission.models.role', \Spatie\Permission\Models\Role::class);
        if (! is_string($class) || ! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Role::class)) {
            throw new \RuntimeException('Configured Spatie role model must be an Eloquent model implementing the Role contract.');
        }

        return $class;
    }

    private function roleAsModel(Role $role): Model
    {
        if (! $role instanceof Model) {
            throw new \InvalidArgumentException('Role must also be an Eloquent model.');
        }

        return $role;
    }
}
