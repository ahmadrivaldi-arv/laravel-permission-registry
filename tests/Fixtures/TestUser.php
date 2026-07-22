<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

final class TestUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];
}
