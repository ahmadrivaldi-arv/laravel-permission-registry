<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\ValueObjects;

final readonly class PermissionRecommendation
{
    private function __construct(
        public string $permission,
        public ?string $reason = null,
    ) {}

    public static function make(string $permission): self
    {
        return new self($permission);
    }

    public function reason(?string $reason): self
    {
        return new self($this->permission, $reason);
    }
}
