<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Exceptions;

final class RegistryValidationException extends PermissionRegistryException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(PHP_EOL, $errors));
    }
}
