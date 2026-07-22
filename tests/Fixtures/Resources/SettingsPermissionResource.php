<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;

final class SettingsPermissionResource extends PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::NONE;

    public static function key(): string
    {
        return 'settings';
    }

    public static function label(): string
    {
        return 'Settings';
    }

    public static function group(): string
    {
        return 'system';
    }

    protected static function actions(): array
    {
        return [PermissionAction::make('manage')];
    }
}
