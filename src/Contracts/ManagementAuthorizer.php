<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Contracts;

use Illuminate\Auth\Access\AuthorizationException;

interface ManagementAuthorizer
{
    /** @throws AuthorizationException */
    public function authorize(object $actor, string $ability): void;
}
