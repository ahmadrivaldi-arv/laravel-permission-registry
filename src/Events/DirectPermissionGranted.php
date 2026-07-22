<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Events;

final readonly class DirectPermissionGranted
{
    public function __construct(public object $user, public string $permission, public object $actor) {}
}
