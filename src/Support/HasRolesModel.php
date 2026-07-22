<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class HasRolesModel
{
    public static function from(object $model): Model
    {
        if (! $model instanceof Model || ! is_callable([$model, 'roles']) || ! is_callable([$model, 'getAllPermissions'])) {
            throw new \InvalidArgumentException('User must be an Eloquent model using Spatie HasRoles.');
        }

        return $model;
    }

    /** @return Collection<int, mixed> */
    public static function roles(Model $model): Collection
    {
        return self::relation($model, 'roles');
    }

    /** @return Collection<int, mixed> */
    public static function relation(Model $model, string $method): Collection
    {
        $relation = self::call($model, $method);
        if (! is_object($relation) || ! is_callable([$relation, 'get'])) {
            throw new \LogicException("Spatie HasRoles [{$method}] must return an Eloquent relation.");
        }
        $items = $relation->get();

        return $items instanceof Collection ? $items : collect($items);
    }

    /** @return Collection<int, mixed> */
    public static function directPermissions(Model $model): Collection
    {
        return self::collection(self::call($model, 'getDirectPermissions'), 'getDirectPermissions');
    }

    /** @return Collection<int, mixed> */
    public static function allPermissions(Model $model): Collection
    {
        return self::collection(self::call($model, 'getAllPermissions'), 'getAllPermissions');
    }

    /** @param list<mixed> $arguments */
    public static function call(Model $model, string $method, array $arguments = []): mixed
    {
        $callback = [$model, $method];
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException('Model ['.$model::class."] does not provide Spatie HasRoles method [{$method}].");
        }

        return $callback(...$arguments);
    }

    /** @return Collection<int, mixed> */
    private static function collection(mixed $value, string $method): Collection
    {
        if (! $value instanceof Collection) {
            throw new \LogicException("Spatie HasRoles [{$method}] must return a collection.");
        }

        return $value;
    }
}
