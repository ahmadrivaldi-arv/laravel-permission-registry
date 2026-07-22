<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Exceptions\RegistryValidationException;
use Illuminate\Console\Command;

final class ValidateCommand extends Command
{
    protected $signature = 'rbac:validate';

    protected $description = 'Validate all permission resource definitions without database writes';

    public function handle(PermissionRegistry $registry): int
    {
        try {
            $registry->validate();
        } catch (RegistryValidationException $exception) {
            foreach ($exception->errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        foreach ($registry->warnings() as $warning) {
            $this->components->warn($warning);
        }
        $this->components->info(count($registry->resources()).' resources and '.count($registry->permissions()).' permissions are valid.');

        return self::SUCCESS;
    }
}
