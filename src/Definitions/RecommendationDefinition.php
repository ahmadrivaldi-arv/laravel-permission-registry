<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Definitions;

final readonly class RecommendationDefinition
{
    public function __construct(
        public string $sourcePermission,
        public string $targetPermission,
        public ?string $reason,
    ) {}
}
