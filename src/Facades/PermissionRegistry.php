<?php

namespace Ahmdrv\PermissionRegistry\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ahmdrv\PermissionRegistry\PermissionRegistry
 */
class PermissionRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ahmdrv\PermissionRegistry\PermissionRegistry::class;
    }
}
