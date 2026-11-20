<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Commands\Concerns\FormatsCommandOutput;
use Ahmdrv\PermissionRegistry\Definitions\SynchronizationResult;
use Ahmdrv\PermissionRegistry\Exceptions\PermissionRegistryException;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Illuminate\Console\Command;

final class SyncCommand extends Command
{
    use FormatsCommandOutput;

    protected $signature = 'rbac:sync
        {--guard= : Spatie guard name}
        {--dry-run : Report changes without writing}
        {--prune : Delete only managed orphan permissions}
        {--force : Skip prune confirmation}';

    protected $description = 'Additively synchronize registered permissions with Spatie';

    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $guard = $this->option('guard') !== null ? (string) $this->option('guard') : null;
        $dryRun = (bool) $this->option('dry-run');
        $pruneRequested = (bool) $this->option('prune');

        try {
            $diff = $synchronizer->diff($guard);
            $prune = $pruneRequested ? $diff->managedOrphans : [];
            if ($prune !== []) {
                $verb = $dryRun ? 'would be' : 'will be';
                $this->components->warn($this->quantity(count($prune), 'managed orphan permission')." {$verb} deleted for guard [{$diff->guard}]:");
                foreach ($prune as $candidate) {
                    $this->entry('<fg=red>−</>', $candidate);
                }
                if (! $dryRun && ! (bool) $this->option('force') && ! $this->confirm('Delete exactly these managed orphan permissions?', false)) {
                    $this->components->warn('Synchronization cancelled; no database changes were made.');

                    return self::FAILURE;
                }
            }

            $result = $synchronizer->sync($guard, $dryRun, $prune);
        } catch (PermissionRegistryException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($result, $pruneRequested);

        return self::SUCCESS;
    }

    private function report(SynchronizationResult $result, bool $pruneRequested): void
    {
        $created = count($result->created);
        $existing = count($result->existing);
        $deleted = count($result->deleted);
        $untouched = count($result->untouched);
        $keptOrphans = count(array_diff($result->untouched, $result->unmanaged));
        [$createdLabel, $deletedLabel] = $result->dryRun ? ['To create', 'To delete'] : ['Created', 'Deleted'];

        $this->heading('Permission sync', "guard [{$result->guard}]".($result->dryRun ? ' · dry run' : ''));
        $this->detail($createdLabel, $this->tally($created, 'green'));
        $this->detail('Existing <fg=gray>already synchronized</>', $existing);
        $this->detail($deletedLabel, $this->tally($deleted, 'red'));
        $this->detail('Untouched <fg=gray>unmanaged or kept orphans</>', $untouched);

        if ($result->created !== []) {
            $this->heading($createdLabel, "({$created})");
            foreach ($result->created as $name) {
                $this->entry('<fg=green>+</>', $name);
            }
        }
        if ($result->deleted !== []) {
            $this->heading($deletedLabel, "({$deleted})");
            foreach ($result->deleted as $name) {
                $this->entry('<fg=red>−</>', $name);
            }
        }

        if ($pruneRequested && $result->deleted === []) {
            $this->newLine();
            $this->hint('No managed orphan permissions to prune.');
        } elseif (! $pruneRequested && $keptOrphans > 0) {
            $this->newLine();
            $this->hint($this->quantity($keptOrphans, 'managed orphan').' kept. Review with rbac:diff, then use --prune to delete.');
        }

        $counts = $result->dryRun
            ? "{$created} to create, {$existing} existing, {$deleted} to delete, {$untouched} untouched"
            : "{$created} created, {$existing} existing, {$deleted} deleted, {$untouched} untouched";

        $message = match (true) {
            $result->dryRun => "Dry run for guard [{$result->guard}]: {$counts}. No changes were written.",
            $created === 0 && $deleted === 0 => "Nothing to synchronize for guard [{$result->guard}]: {$counts}.",
            default => "Synchronization complete for guard [{$result->guard}]: {$counts}.",
        };
        $this->components->info($message);
    }
}
