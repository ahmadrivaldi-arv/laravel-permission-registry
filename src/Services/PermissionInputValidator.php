<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Services;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Exceptions\GuardMismatch;
use Ahmdrv\PermissionRegistry\Exceptions\UnregisteredPermission;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

final readonly class PermissionInputValidator
{
    public function __construct(private PermissionRegistry $registry, private Repository $config) {}

    /** @param iterable<string> $permissions
     * @return list<string>
     */
    public function registered(iterable $permissions): array
    {
        $normalized = [];
        foreach ($permissions as $permission) {
            if (! $this->registry->hasPermission($permission)) {
                throw new UnregisteredPermission("Permission [{$permission}] is not registered in code. Synchronize the registry or correct the permission name.");
            }
            $normalized[$permission] = true;
        }
        $names = array_keys($normalized);
        sort($names, SORT_STRING);

        return $names;
    }

    /** @param iterable<string> $permissions
     * @return list<string>
     */
    public function registeredForGuard(iterable $permissions, string $guard): array
    {
        $names = $this->registered($permissions);
        if ($names === []) {
            return [];
        }
        $class = $this->config->get('permission.models.permission', Permission::class);
        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new \RuntimeException('Configured Spatie permission model must extend Eloquent Model.');
        }
        $found = $class::query()->where('guard_name', $guard)->whereIn('name', $names)->pluck('name')->all();
        $missing = array_values(array_diff($names, $found));
        if ($missing !== []) {
            throw new GuardMismatch("Registered permissions are not synchronized for guard [{$guard}]: ".implode(', ', $missing).'.');
        }

        return $names;
    }
}
