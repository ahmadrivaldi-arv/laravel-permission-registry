<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Authorization;

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Illuminate\Contracts\Auth\Access\Gate;

final readonly class GateManagementAuthorizer implements ManagementAuthorizer
{
    public function __construct(private Gate $gate) {}

    public function authorize(object $actor, string $ability): void
    {
        $this->gate->forUser($actor)->authorize($ability);
    }
}
