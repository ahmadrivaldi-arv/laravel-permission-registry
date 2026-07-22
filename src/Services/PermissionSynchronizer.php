<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Services;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Definitions\PermissionDiff;
use Ahmdrv\PermissionRegistry\Definitions\SynchronizationResult;
use Ahmdrv\PermissionRegistry\Events\RegistrySynchronized;
use Ahmdrv\PermissionRegistry\Exceptions\UnsafePruneRequest;
use Ahmdrv\PermissionRegistry\Support\GuardResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;

final readonly class PermissionSynchronizer
{
    public function __construct(
        private PermissionRegistry $registry,
        private GuardResolver $guards,
        private Repository $config,
        private PermissionRegistrar $cache,
        private Dispatcher $events,
    ) {}

    public function diff(?string $guard = null): PermissionDiff
    {
        $this->registry->validate();
        $guard = $this->guards->resolve($guard);
        $registered = array_map(static fn ($permission): string => $permission->name, $this->registry->permissions());
        sort($registered, SORT_STRING);
        $database = $this->permissionModel()::query()->where('guard_name', $guard)->pluck('name')->all();
        $database = array_values(array_filter($database, 'is_string'));
        sort($database, SORT_STRING);

        $missing = array_values(array_diff($registered, $database));
        $synchronized = array_values(array_intersect($registered, $database));
        $orphans = array_values(array_diff($database, $registered));
        $resourceKeys = array_fill_keys(array_map(static fn ($resource): string => $resource->key, $this->registry->resources()), true);
        $managed = [];
        $unmanaged = [];
        foreach ($orphans as $name) {
            $parts = explode('.', $name);
            if (count($parts) === 2 && isset($resourceKeys[$parts[0]]) && preg_match('/^[a-z][a-z0-9_]*$/', $parts[1])) {
                $managed[] = $name;
            } else {
                $unmanaged[] = $name;
            }
        }

        return new PermissionDiff($guard, $missing, $synchronized, $managed, $unmanaged);
    }

    /** @param list<string>|null $pruneCandidates */
    public function sync(?string $guard = null, bool $dryRun = false, ?array $pruneCandidates = null): SynchronizationResult
    {
        $diff = $this->diff($guard);
        $candidates = $pruneCandidates ?? [];
        $unsafe = array_values(array_diff($candidates, $diff->managedOrphans));
        if ($unsafe !== []) {
            throw new UnsafePruneRequest('Refusing to prune permissions outside the managed-orphan boundary: '.implode(', ', $unsafe).'.');
        }
        sort($candidates, SORT_STRING);

        $untouched = array_values(array_unique([...array_diff($diff->managedOrphans, $candidates), ...$diff->unmanaged]));
        sort($untouched, SORT_STRING);
        $result = new SynchronizationResult($diff->guard, $diff->missing, $diff->synchronized, $candidates, $diff->unmanaged, $untouched, $dryRun);
        if ($dryRun) {
            return $result;
        }
        if ($diff->missing === [] && $candidates === []) {
            return $result;
        }

        $modelClass = $this->permissionModel();
        $model = new $modelClass;
        $connection = $model->getConnection();
        $connection->transaction(function () use ($modelClass, $diff, $candidates): void {
            foreach ($diff->missing as $name) {
                $modelClass::query()->create(['name' => $name, 'guard_name' => $diff->guard]);
            }
            if ($candidates !== []) {
                $modelClass::query()->where('guard_name', $diff->guard)->whereIn('name', $candidates)->delete();
            }
        });

        $this->cache->forgetCachedPermissions();
        $this->events->dispatch(new RegistrySynchronized($result));

        return $result;
    }

    /** @return class-string<Model&Permission> */
    private function permissionModel(): string
    {
        $class = $this->config->get('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        if (! is_string($class) || ! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Permission::class)) {
            throw new \RuntimeException('Configured Spatie permission model must be an Eloquent model implementing the Permission contract.');
        }

        return $class;
    }
}
