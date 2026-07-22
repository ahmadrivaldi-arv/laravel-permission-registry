<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Services;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Events\UserRolesSynchronized;
use Ahmdrv\PermissionRegistry\Exceptions\GuardMismatch;
use Ahmdrv\PermissionRegistry\Support\GuardResolver;
use Ahmdrv\PermissionRegistry\Support\HasRolesModel;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final readonly class UserRoleManager
{
    public function __construct(
        private ManagementAuthorizer $authorizer,
        private GuardResolver $guards,
        private Repository $config,
        private PermissionRegistrar $cache,
        private Dispatcher $events,
    ) {}

    /** @param iterable<string> $roles */
    public function sync(object $actor, object $user, iterable $roles): object
    {
        $this->authorizer->authorize($actor, $this->ability());
        $model = $this->userModel($user);
        $guard = $this->guards->resolve();
        $this->guards->assertCompatible($model, $guard);
        $names = array_values(array_unique(is_array($roles) ? $roles : iterator_to_array($roles)));
        sort($names, SORT_STRING);
        $this->assertRolesExist($names, $guard);

        $current = HasRolesModel::roles($model)->where('guard_name', $guard)->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->sort()->values()->all();
        if ($current === $names) {
            return $user;
        }

        $model->getConnection()->transaction(fn () => HasRolesModel::call($model, 'syncRoles', [$names]));
        $this->cache->forgetCachedPermissions();
        $this->events->dispatch(new UserRolesSynchronized($user, $names, $actor));

        return $user;
    }

    public function assign(object $actor, object $user, string $role): object
    {
        $model = $this->userModel($user);
        $current = HasRolesModel::roles($model)->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->all();

        return $this->sync($actor, $user, [...$current, $role]);
    }

    public function remove(object $actor, object $user, string $role): object
    {
        $model = $this->userModel($user);
        $current = HasRolesModel::roles($model)->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->reject(fn (string $name): bool => $name === $role)->all();

        return $this->sync($actor, $user, $current);
    }

    /** @param list<string> $names */
    private function assertRolesExist(array $names, string $guard): void
    {
        if ($names === []) {
            return;
        }
        $roleClass = $this->config->get('permission.models.role', Role::class);
        if (! is_string($roleClass) || ! is_subclass_of($roleClass, Model::class)) {
            throw new \RuntimeException('Configured Spatie role model must extend Eloquent Model.');
        }
        $found = $roleClass::query()->where('guard_name', $guard)->whereIn('name', $names)->pluck('name')->all();
        $missing = array_values(array_diff($names, $found));
        if ($missing !== []) {
            throw new GuardMismatch("Roles do not exist for guard [{$guard}]: ".implode(', ', $missing).'.');
        }
    }

    private function userModel(object $user): Model
    {
        return HasRolesModel::from($user);
    }

    private function ability(): string
    {
        $ability = $this->config->get('permission-registry.management_abilities.assign_user_roles');
        if (! is_string($ability) || $ability === '') {
            throw new \LogicException('Management ability [assign_user_roles] is not configured.');
        }

        return $ability;
    }
}
