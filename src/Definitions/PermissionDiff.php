<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Definitions;

final readonly class PermissionDiff
{
    /**
     * @param  list<string>  $missing
     * @param  list<string>  $synchronized
     * @param  list<string>  $managedOrphans
     * @param  list<string>  $unmanaged
     */
    public function __construct(
        public string $guard,
        public array $missing,
        public array $synchronized,
        public array $managedOrphans,
        public array $unmanaged,
    ) {}
}
