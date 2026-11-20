<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Commands\Concerns\FormatsCommandOutput;
use Ahmdrv\PermissionRegistry\Exceptions\PermissionRegistryException;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Illuminate\Console\Command;

final class DiffCommand extends Command
{
    use FormatsCommandOutput;

    protected $signature = 'rbac:diff {--guard= : Spatie guard name} {--json : Emit machine-readable JSON}';

    protected $description = 'Compare registered permissions with Spatie permission rows';

    public function handle(PermissionSynchronizer $synchronizer): int
    {
        try {
            $diff = $synchronizer->diff($this->option('guard') !== null ? (string) $this->option('guard') : null);
        } catch (PermissionRegistryException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'guard' => $diff->guard,
                'missing' => $diff->missing,
                'synchronized' => $diff->synchronized,
                'managed_orphans' => $diff->managedOrphans,
                'unmanaged' => $diff->unmanaged,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $verbose = $this->output->isVerbose();
        $categories = [
            ['Missing', 'in code, not in the database', $diff->missing, 'yellow', '+', true],
            ['Synchronized', 'in code and the database', $diff->synchronized, 'green', '✓', $verbose],
            ['Managed orphans', 'database only, under a registered resource', $diff->managedOrphans, 'red', '−', true],
            ['Unmanaged', 'database only, never modified by this package', $diff->unmanaged, 'gray', '·', true],
        ];

        $this->heading('Permission diff', "guard [{$diff->guard}]");
        foreach ($categories as [$title, $meaning, $names, $color]) {
            $this->detail("{$title} <fg=gray>{$meaning}</>", $this->tally(count($names), $color));
        }

        foreach ($categories as [$title, , $names, $color, $marker, $expanded]) {
            if ($names === [] || ! $expanded) {
                continue;
            }
            $this->heading($title, '('.count($names).')');
            foreach ($names as $name) {
                $this->entry("<fg={$color}>{$marker}</>", $name);
            }
        }

        if (! $verbose && $diff->synchronized !== []) {
            $this->newLine();
            $this->hint('Run with -v to list synchronized permissions.');
        }

        if ($diff->missing === [] && $diff->managedOrphans === []) {
            $this->components->info("The database matches the registry for guard [{$diff->guard}].");

            return self::SUCCESS;
        }

        $this->heading('Next steps');
        if ($diff->missing !== []) {
            $this->entry('<fg=gray>→</>', 'php artisan rbac:sync', 'creates '.$this->quantity(count($diff->missing), 'missing permission'));
        }
        if ($diff->managedOrphans !== []) {
            $this->entry('<fg=gray>→</>', 'php artisan rbac:sync --prune', 'reviews '.$this->quantity(count($diff->managedOrphans), 'managed orphan').' for deletion');
        }
        $this->newLine();

        return self::SUCCESS;
    }
}
