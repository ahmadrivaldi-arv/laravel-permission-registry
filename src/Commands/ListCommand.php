<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Definitions\PermissionDefinition;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Exceptions\RegistryValidationException;
use Illuminate\Console\Command;

final class ListCommand extends Command
{
    protected $signature = 'rbac:list
        {--group= : Filter by group key}
        {--resource= : Filter by resource key}
        {--risk= : Filter by risk level}
        {--with-recommendations : Include advisory recommendations}
        {--json : Emit machine-readable JSON}';

    protected $description = 'List registered permission definitions';

    public function handle(PermissionRegistry $registry): int
    {
        try {
            $permissions = array_values(array_filter($registry->permissions(), fn (PermissionDefinition $permission): bool => $this->matches($permission)));
        } catch (RegistryValidationException $exception) {
            foreach ($exception->errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $payload = array_map(fn (PermissionDefinition $permission): array => $this->json($permission), $permissions);
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $withRecommendations = (bool) $this->option('with-recommendations');
        $headers = ['Group', 'Resource', 'Action', 'Permission', 'Risk', 'Direct'];
        if ($withRecommendations) {
            $headers[] = 'Recommendations';
        }
        $rows = array_map(static function (PermissionDefinition $permission) use ($withRecommendations): array {
            $row = [$permission->groupKey, $permission->resourceKey, $permission->actionKey, $permission->name, $permission->risk->value, $permission->directGrantable ? 'yes' : 'no'];
            if ($withRecommendations) {
                $row[] = implode('; ', array_map(static fn ($recommendation): string => $recommendation->targetPermission.($recommendation->reason ? " ({$recommendation->reason})" : ''), $permission->recommendations));
            }

            return $row;
        }, $permissions);
        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    private function matches(PermissionDefinition $permission): bool
    {
        $risk = $this->option('risk');
        if ($risk !== null && RiskLevel::tryFrom((string) $risk) === null) {
            throw new RegistryValidationException(['Invalid risk filter. Supported values: low, medium, high, critical.']);
        }

        return ($this->option('group') === null || $permission->groupKey === $this->option('group'))
            && ($this->option('resource') === null || $permission->resourceKey === $this->option('resource'))
            && ($risk === null || $permission->risk->value === $risk);
    }

    /** @return array<string, mixed> */
    private function json(PermissionDefinition $permission): array
    {
        return [
            'group' => $permission->groupKey,
            'resource' => $permission->resourceKey,
            'action' => $permission->actionKey,
            'permission' => $permission->name,
            'label' => $permission->label,
            'description' => $permission->description,
            'risk' => $permission->risk->value,
            'direct_grantable' => $permission->directGrantable,
            'recommendations' => array_map(static fn ($recommendation): array => ['permission' => $recommendation->targetPermission, 'reason' => $recommendation->reason], $permission->recommendations),
        ];
    }
}
