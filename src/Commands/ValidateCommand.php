<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Commands\Concerns\FormatsCommandOutput;
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Exceptions\RegistryValidationException;
use Illuminate\Console\Command;

final class ValidateCommand extends Command
{
    use FormatsCommandOutput;

    protected $signature = 'rbac:validate';

    protected $description = 'Validate all permission resource definitions without database writes';

    public function handle(PermissionRegistry $registry): int
    {
        try {
            $registry->validate();
        } catch (RegistryValidationException $exception) {
            $this->components->error('Permission registry is invalid: '.$this->quantity(count($exception->errors), 'error').' found.');
            foreach ($exception->errors as $error) {
                $this->entry('<fg=red>✕</>', $error);
            }
            $this->newLine();

            return self::FAILURE;
        }

        $resources = count($registry->resources());
        $permissions = count($registry->permissions());
        $warnings = $registry->warnings();

        $this->heading('Permission registry');
        $this->detail('Groups', count($registry->resourcesByGroup()));
        $this->detail('Resources', $resources);
        $this->detail('Permissions', $permissions);
        $this->detail('Recommendations <fg=gray>advisory only</>', count($registry->recommendations()));
        $this->detail('Warnings', $this->tally(count($warnings), 'yellow'));

        if ($warnings !== []) {
            $this->heading('Warnings', 'these do not affect authorization');
            foreach ($warnings as $warning) {
                $this->entry('<fg=yellow>!</>', $warning);
            }
        }

        $this->components->info($this->quantity($resources, 'resource').' and '.$this->quantity($permissions, 'permission').' are valid.');

        return self::SUCCESS;
    }
}
