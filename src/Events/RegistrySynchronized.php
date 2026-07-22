<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Events;

use Ahmdrv\PermissionRegistry\Definitions\SynchronizationResult;

final readonly class RegistrySynchronized
{
    public function __construct(public SynchronizationResult $result) {}
}
