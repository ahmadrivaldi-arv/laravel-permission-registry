<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Events;

use Spatie\Permission\Contracts\Role;

final readonly class RolePermissionsSynchronized
{
    /** @param list<string> $permissions */
    public function __construct(public Role $role, public array $permissions, public object $actor) {}
}
