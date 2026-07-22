<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Definitions;

final readonly class SynchronizationResult
{
    /**
     * @param  list<string>  $created
     * @param  list<string>  $existing
     * @param  list<string>  $deleted
     * @param  list<string>  $unmanaged
     * @param  list<string>  $untouched
     */
    public function __construct(
        public string $guard,
        public array $created,
        public array $existing,
        public array $deleted,
        public array $unmanaged,
        public array $untouched,
        public bool $dryRun,
    ) {}
}
