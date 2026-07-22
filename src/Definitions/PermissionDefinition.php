<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Definitions;

use Ahmdrv\PermissionRegistry\Enums\RiskLevel;

final readonly class PermissionDefinition
{
    /** @param list<RecommendationDefinition> $recommendations */
    public function __construct(
        public string $actionKey,
        public string $name,
        public string $label,
        public ?string $description,
        public RiskLevel $risk,
        public bool $directGrantable,
        public array $recommendations,
        public string $resourceKey,
        public string $resourceLabel,
        public string $groupKey,
    ) {}
}
