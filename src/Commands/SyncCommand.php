<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Exceptions\PermissionRegistryException;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Illuminate\Console\Command;

final class SyncCommand extends Command
{
    protected $signature = 'rbac:sync
        {--guard= : Spatie guard name}
        {--dry-run : Report changes without writing}
        {--prune : Delete only managed orphan permissions}
        {--force : Skip prune confirmation}';

    protected $description = 'Additively synchronize registered permissions with Spatie';

    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $guard = $this->option('guard') !== null ? (string) $this->option('guard') : null;
        try {
            $diff = $synchronizer->diff($guard);
            $prune = (bool) $this->option('prune') ? $diff->managedOrphans : [];
            if ($prune !== []) {
                $this->components->warn('Managed orphan deletion candidates:');
                foreach ($prune as $candidate) {
                    $this->line(" - {$candidate}");
                }
                if (! (bool) $this->option('dry-run') && ! (bool) $this->option('force') && ! $this->confirm('Delete exactly these managed orphan permissions?', false)) {
                    $this->components->warn('Synchronization cancelled; no database changes were made.');

                    return self::FAILURE;
                }
            }

            $result = $synchronizer->sync($guard, (bool) $this->option('dry-run'), $prune);
        } catch (PermissionRegistryException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $mode = $result->dryRun ? 'Dry run' : 'Synchronization complete';
        $this->components->info("{$mode} for guard [{$result->guard}]: ".count($result->created).' created, '.count($result->existing).' existing, '.count($result->deleted).' deleted, '.count($result->untouched).' untouched.');

        return self::SUCCESS;
    }
}
