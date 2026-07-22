<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Definitions;

final readonly class EffectivePermission
{
    /** @param list<string> $roles */
    public function __construct(
        public string $permission,
        public bool $direct,
        public array $roles,
        public bool $registered,
    ) {}
}
