<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;

final class AllowAllAuthorizer implements ManagementAuthorizer
{
    public function authorize(object $actor, string $ability): void {}
}
