<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Definitions;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;

final readonly class ResourceDefinition
{
    /** @param list<PermissionDefinition> $actions */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $description,
        public string $groupKey,
        public PermissionPreset $preset,
        public array $actions,
        public string $resourceClass,
    ) {}
}
