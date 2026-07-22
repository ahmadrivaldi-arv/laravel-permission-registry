<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;

final class DenyAllAuthorizer implements ManagementAuthorizer
{
    public function authorize(object $actor, string $ability): void
    {
        throw new AuthorizationException("Denied [{$ability}].");
    }
}
