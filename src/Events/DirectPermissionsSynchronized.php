<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Events;

final readonly class DirectPermissionsSynchronized
{
    /** @param list<string> $permissions */
    public function __construct(public object $user, public array $permissions, public object $actor) {}
}
