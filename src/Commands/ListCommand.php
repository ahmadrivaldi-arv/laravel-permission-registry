<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Commands\Concerns\FormatsCommandOutput;
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Definitions\PermissionDefinition;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Exceptions\RegistryValidationException;
use Illuminate\Console\Command;

final class ListCommand extends Command
{
    use FormatsCommandOutput;

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

        $filters = $this->activeFilters();
        if ($permissions === []) {
            $this->components->warn($filters === [] ? 'No permissions are registered.' : 'No permissions match the filters: '.implode(', ', $filters).'.');

            return self::SUCCESS;
        }

        $this->heading('Registered permissions', $filters === [] ? null : 'filtered by '.implode(', ', $filters));

        /** @var array<string, array<string, list<PermissionDefinition>>> $tree */
        $tree = [];
        foreach ($permissions as $permission) {
            $tree[$permission->groupKey][$permission->resourceKey][] = $permission;
        }

        $withRecommendations = (bool) $this->option('with-recommendations');
        $resourceCount = 0;
        foreach ($tree as $group => $resources) {
            $this->newLine();
            $this->line('  <fg=green;options=bold>'.$this->escape(strtoupper($group)).'</>');
            foreach ($resources as $resourceKey => $resourcePermissions) {
                $resourceCount++;
                $this->renderResource($registry, $resourceKey, $resourcePermissions, $withRecommendations);
            }
        }

        $this->newLine();
        $this->hint('Showing '.$this->quantity(count($permissions), 'permission').' across '.$this->quantity($resourceCount, 'resource').' in '.$this->quantity(count($tree), 'group').'.');
        $this->hint('direct = eligible for direct user grants when enabled · role only = assignable through roles only');
        $this->newLine();

        return self::SUCCESS;
    }

    /** @param list<PermissionDefinition> $permissions */
    private function renderResource(PermissionRegistry $registry, string $resourceKey, array $permissions, bool $withRecommendations): void
    {
        $resource = $registry->findResource($resourceKey);
        $meta = [$resourceKey];
        if ($resource !== null) {
            $meta[] = $resource->preset->value;
        }
        $meta[] = $this->quantity(count($permissions), 'permission');

        $this->newLine();
        $this->line('  <options=bold>'.$this->escape($permissions[0]->resourceLabel).'</> <fg=gray>'.$this->escape(implode(' · ', $meta)).'</>');
        if ($resource?->description !== null && $resource->description !== '') {
            $this->hint($resource->description);
        }

        foreach ($permissions as $permission) {
            $this->detail(
                $this->escape($permission->name).' <fg=gray>'.$this->escape($permission->label).'</>',
                $this->riskBadge($permission->risk).' <fg=gray>·</> '.($permission->directGrantable ? '<fg=cyan>direct</>' : '<fg=gray>role only</>'),
            );

            if ($withRecommendations) {
                foreach ($permission->recommendations as $recommendation) {
                    $this->entry('  <fg=gray>↳ recommends</>', $recommendation->targetPermission, $recommendation->reason !== null ? "— {$recommendation->reason}" : null);
                }
            }
        }
    }

    /** @return list<string> */
    private function activeFilters(): array
    {
        $filters = [];
        foreach (['group', 'resource', 'risk'] as $option) {
            $value = $this->option($option);
            if ($value !== null) {
                $filters[] = "{$option}={$value}";
            }
        }

        return $filters;
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
