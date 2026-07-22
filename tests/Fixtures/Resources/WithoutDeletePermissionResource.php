<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources;

use Ahmdrv\PermissionRegistry\Resources\PermissionResource;

final class WithoutDeletePermissionResource extends PermissionResource
{
    public static function key(): string
    {
        return 'documents';
    }

    public static function label(): string
    {
        return 'Documents';
    }

    public static function group(): string
    {
        return 'content';
    }

    protected static function exceptActions(): array
    {
        return ['delete'];
    }
}
