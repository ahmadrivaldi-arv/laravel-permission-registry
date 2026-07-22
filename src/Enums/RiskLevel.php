<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Enums;

enum RiskLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
