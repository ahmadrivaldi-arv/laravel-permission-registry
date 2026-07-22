<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Exceptions\PermissionRegistryException;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Illuminate\Console\Command;

final class DiffCommand extends Command
{
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

        $this->components->info("Permission diff for guard [{$diff->guard}].");
        $this->table(['Category', 'Permission'], $this->rows([
            'missing' => $diff->missing,
            'synchronized' => $diff->synchronized,
            'managed orphan' => $diff->managedOrphans,
            'unmanaged' => $diff->unmanaged,
        ]));

        return self::SUCCESS;
    }

    /** @param array<string, list<string>> $categories
     * @return list<array{string, string}>
     */
    private function rows(array $categories): array
    {
        $rows = [];
        foreach ($categories as $category => $permissions) {
            foreach ($permissions as $permission) {
                $rows[] = [$category, $permission];
            }
        }

        return $rows;
    }
}
