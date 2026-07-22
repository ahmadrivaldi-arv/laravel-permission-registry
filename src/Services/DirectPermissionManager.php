<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Services;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Events\DirectPermissionGranted;
use Ahmdrv\PermissionRegistry\Events\DirectPermissionRevoked;
use Ahmdrv\PermissionRegistry\Events\DirectPermissionsSynchronized;
use Ahmdrv\PermissionRegistry\Exceptions\DirectPermissionsDisabled;
use Ahmdrv\PermissionRegistry\Exceptions\PermissionNotDirectGrantable;
use Ahmdrv\PermissionRegistry\Support\GuardResolver;
use Ahmdrv\PermissionRegistry\Support\HasRolesModel;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final readonly class DirectPermissionManager
{
    public function __construct(
        private ManagementAuthorizer $authorizer,
        private PermissionRegistry $registry,
        private PermissionInputValidator $permissions,
        private GuardResolver $guards,
        private Repository $config,
        private PermissionRegistrar $cache,
        private Dispatcher $events,
    ) {}

    public function grant(object $actor, object $user, string $permission): object
    {
        $this->enabled();
        $this->authorizer->authorize($actor, $this->ability());
        $this->grantable([$permission]);
        $model = $this->userModel($user);
        $guard = $this->guards->resolve();
        $this->guards->assertCompatible($model, $guard);
        $this->permissions->registeredForGuard([$permission], $guard);
        if (HasRolesModel::directPermissions($model)->contains('name', $permission)) {
            return $user;
        }

        $model->getConnection()->transaction(fn () => HasRolesModel::call($model, 'givePermissionTo', [$permission]));
        $this->cache->forgetCachedPermissions();
        $this->events->dispatch(new DirectPermissionGranted($user, $permission, $actor));

        return $user;
    }

    public function revoke(object $actor, object $user, string $permission): object
    {
        $this->enabled();
        $this->authorizer->authorize($actor, $this->ability());
        $this->permissions->registered([$permission]);
        $model = $this->userModel($user);
        $guard = $this->guards->resolve();
        $this->guards->assertCompatible($model, $guard);
        $this->permissions->registeredForGuard([$permission], $guard);
        if (! HasRolesModel::directPermissions($model)->contains('name', $permission)) {
            return $user;
        }

        $model->getConnection()->transaction(fn () => HasRolesModel::call($model, 'revokePermissionTo', [$permission]));
        $this->cache->forgetCachedPermissions();
        $this->events->dispatch(new DirectPermissionRevoked($user, $permission, $actor));

        return $user;
    }

    /** @param iterable<string> $permissions */
    public function sync(object $actor, object $user, iterable $permissions): object
    {
        $this->enabled();
        $this->authorizer->authorize($actor, $this->ability());
        $names = $this->grantable($permissions);
        $model = $this->userModel($user);
        $guard = $this->guards->resolve();
        $this->guards->assertCompatible($model, $guard);
        $this->permissions->registeredForGuard($names, $guard);
        $current = HasRolesModel::directPermissions($model)->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->sort()->values()->all();
        if ($current === $names) {
            return $user;
        }

        $model->getConnection()->transaction(fn () => HasRolesModel::call($model, 'syncPermissions', [$names]));
        $this->cache->forgetCachedPermissions();
        $this->events->dispatch(new DirectPermissionsSynchronized($user, $names, $actor));

        return $user;
    }

    /** @param iterable<string> $permissions
     * @return list<string>
     */
    private function grantable(iterable $permissions): array
    {
        $names = $this->permissions->registered($permissions);
        foreach ($names as $name) {
            if (! $this->registry->findPermission($name)?->directGrantable) {
                throw new PermissionNotDirectGrantable("Permission [{$name}] is not marked directGrantable and may only be assigned through roles.");
            }
        }

        return $names;
    }

    private function enabled(): void
    {
        if (! (bool) $this->config->get('permission-registry.direct_permissions.enabled', false)) {
            throw new DirectPermissionsDisabled('Direct user permissions are disabled. Enable [permission-registry.direct_permissions.enabled] explicitly before mutating them.');
        }
    }

    private function userModel(object $user): Model
    {
        return HasRolesModel::from($user);
    }

    private function ability(): string
    {
        $ability = $this->config->get('permission-registry.management_abilities.manage_direct_permissions');
        if (! is_string($ability) || $ability === '') {
            throw new \LogicException('Management ability [manage_direct_permissions] is not configured.');
        }

        return $ability;
    }
}
