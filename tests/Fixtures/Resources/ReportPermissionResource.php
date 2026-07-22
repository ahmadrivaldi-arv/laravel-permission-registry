<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;

final class ReportPermissionResource extends PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::READ_ONLY;

    public static function key(): string
    {
        return 'reports';
    }

    public static function label(): string
    {
        return 'Reports';
    }

    public static function group(): string
    {
        return 'analytics';
    }
}
