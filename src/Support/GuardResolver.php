<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Support;

use Ahmdrv\PermissionRegistry\Exceptions\GuardMismatch;
use Illuminate\Contracts\Config\Repository;
use Spatie\Permission\Guard;

final readonly class GuardResolver
{
    public function __construct(private Repository $config) {}

    public function resolve(?string $guard = null): string
    {
        $resolved = $guard ?? $this->config->get('permission-registry.guard') ?? $this->config->get('auth.defaults.guard');
        if (! is_string($resolved) || trim($resolved) === '') {
            throw new GuardMismatch('Unable to resolve a permission guard. Set [permission-registry.guard] or [auth.defaults.guard].');
        }

        return $resolved;
    }

    public function forModel(object $model): string
    {
        return Guard::getDefaultName($model);
    }

    public function assertCompatible(object $model, string $guard): void
    {
        $guards = Guard::getNames($model);
        if (! $guards->contains($guard)) {
            throw new GuardMismatch('Model ['.$model::class."] does not support guard [{$guard}]; supported guards: [".$guards->implode(', ').'].');
        }
    }
}
