<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Services;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Definitions\EffectivePermission;
use Ahmdrv\PermissionRegistry\Support\HasRolesModel;
use Illuminate\Database\Eloquent\Model;

final readonly class AccessInspector
{
    public function __construct(private PermissionRegistry $registry) {}

    /** @return list<EffectivePermission> */
    public function inspect(object $user): array
    {
        $model = HasRolesModel::from($user);
        $direct = array_fill_keys(HasRolesModel::directPermissions($model)->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->all(), true);
        $rolesByPermission = [];
        foreach (HasRolesModel::roles($model) as $role) {
            if (! $role instanceof Model) {
                continue;
            }
            foreach (HasRolesModel::relation($role, 'permissions') as $permission) {
                if (! $permission instanceof Model) {
                    continue;
                }
                $rolesByPermission[(string) $permission->getAttribute('name')][] = (string) $role->getAttribute('name');
            }
        }

        $result = [];
        foreach (HasRolesModel::allPermissions($model)->sortBy('name') as $permission) {
            if (! $permission instanceof Model) {
                continue;
            }
            $name = (string) $permission->getAttribute('name');
            $roles = array_values(array_unique($rolesByPermission[$name] ?? []));
            sort($roles, SORT_STRING);
            $result[] = new EffectivePermission($name, isset($direct[$name]), $roles, $this->registry->hasPermission($name));
        }

        return $result;
    }
}
