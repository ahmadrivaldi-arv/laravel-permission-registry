<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Contracts;

use Ahmdrv\PermissionRegistry\Definitions\PermissionDefinition;
use Ahmdrv\PermissionRegistry\Definitions\RecommendationDefinition;
use Ahmdrv\PermissionRegistry\Definitions\ResourceDefinition;

interface PermissionRegistry
{
    public function register(string $resourceClass): self;

    /** @param iterable<class-string> $resourceClasses */
    public function registerMany(iterable $resourceClasses): self;

    /** @return list<ResourceDefinition> */
    public function resources(): array;

    /** @return list<PermissionDefinition> */
    public function permissions(): array;

    /** @return list<RecommendationDefinition> */
    public function recommendations(): array;

    /** @return array<string, list<ResourceDefinition>> */
    public function resourcesByGroup(): array;

    /** @return array<string, list<PermissionDefinition>> */
    public function permissionsByGroup(): array;

    public function findResource(string $key): ?ResourceDefinition;

    public function findPermission(string $name): ?PermissionDefinition;

    public function hasPermission(string $name): bool;

    /** @return list<string> */
    public function warnings(): array;

    public function validate(): void;
}
