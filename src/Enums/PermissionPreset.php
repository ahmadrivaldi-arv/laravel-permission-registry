<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Enums;

enum PermissionPreset: string
{
    case CRUD = 'crud';
    case READ_ONLY = 'read-only';
    case NONE = 'none';
}
