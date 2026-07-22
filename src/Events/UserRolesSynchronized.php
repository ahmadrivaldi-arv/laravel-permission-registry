<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Events;

final readonly class UserRolesSynchronized
{
    /** @param list<string> $roles */
    public function __construct(public object $user, public array $roles, public object $actor) {}
}
