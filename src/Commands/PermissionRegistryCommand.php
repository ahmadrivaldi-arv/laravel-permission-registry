<?php

namespace Ahmdrv\PermissionRegistry\Commands;

use Illuminate\Console\Command;

class PermissionRegistryCommand extends Command
{
    public $signature = 'laravel-permission-registry';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
