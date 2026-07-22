<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Resources;

use Ahmdrv\PermissionRegistry\Definitions\PermissionDefinition;
use Ahmdrv\PermissionRegistry\Definitions\RecommendationDefinition;
use Ahmdrv\PermissionRegistry\Definitions\ResourceDefinition;
use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Exceptions\InvalidResourceDefinition;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;
use Illuminate\Support\Str;

abstract class PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::CRUD;

    abstract public static function key(): string;

    abstract public static function label(): string;

    abstract public static function group(): string;

    public static function description(): ?string
    {
        return null;
    }

    /** @return list<PermissionAction> */
    protected static function actions(): array
    {
        return [];
    }

    /** @return list<string> */
    protected static function exceptActions(): array
    {
        return [];
    }

    final public static function definition(): ResourceDefinition
    {
        $class = static::class;
        $resourceKey = static::key();
        $label = trim(static::label());
        $group = static::group();

        self::assertKey($resourceKey, 'resource', $class);
        self::assertKey($group, 'group', $class);

        if ($label === '') {
            throw new InvalidResourceDefinition("Resource [{$class}] must define a non-empty label.");
        }

        $actions = self::presetActions(static::$preset);
        $knownCustom = [];

        foreach (self::rawActions() as $index => $custom) {
            if (! $custom instanceof PermissionAction) {
                $type = get_debug_type($custom);
                throw new InvalidResourceDefinition("Resource [{$class}] action at index [{$index}] must be a PermissionAction; [{$type}] given.");
            }

            self::assertKey($custom->key, 'action', $class);
            if (isset($knownCustom[$custom->key])) {
                throw new InvalidResourceDefinition("Resource [{$class}] declares custom action [{$custom->key}] more than once.");
            }

            $knownCustom[$custom->key] = true;
            $actions[$custom->key] = isset($actions[$custom->key])
                ? self::merge($actions[$custom->key], $custom)
                : self::withDefaults($custom);
        }

        $exclusions = self::rawExclusions();
        foreach ($exclusions as $index => $excluded) {
            if (! is_string($excluded)) {
                throw new InvalidResourceDefinition("Resource [{$class}] exclusion at index [{$index}] must be a string.");
            }
            self::assertKey($excluded, 'excluded action', $class);
            if (! array_key_exists($excluded, $actions)) {
                throw new InvalidResourceDefinition("Resource [{$class}] excludes unknown action [{$excluded}]. Check for a typo or declare the action first.");
            }
            unset($actions[$excluded]);
        }

        $definitions = [];
        foreach ($actions as $action) {
            $permissionName = "{$resourceKey}.{$action->key}";
            if ($action->hasLabel && trim((string) $action->actionLabel) === '') {
                throw new InvalidResourceDefinition("Resource [{$class}] action [{$action->key}] must define a non-empty label when overriding it.");
            }
            $recommendations = [];
            foreach ($action->recommendations as $recommendation) {
                $recommendations[] = new RecommendationDefinition($permissionName, $recommendation->permission, $recommendation->reason);
            }

            $definitions[] = new PermissionDefinition(
                actionKey: $action->key,
                name: $permissionName,
                label: $action->actionLabel ?? Str::headline($action->key),
                description: $action->actionDescription,
                risk: $action->riskLevel ?? RiskLevel::MEDIUM,
                directGrantable: $action->isDirectGrantable ?? false,
                recommendations: $recommendations,
                resourceKey: $resourceKey,
                resourceLabel: $label,
                groupKey: $group,
            );
        }

        return new ResourceDefinition($resourceKey, $label, static::description(), $group, static::$preset, $definitions, $class);
    }

    /** @return array<string, PermissionAction> */
    private static function presetActions(PermissionPreset $preset): array
    {
        $actions = match ($preset) {
            PermissionPreset::CRUD => [
                self::standard('view_any', RiskLevel::LOW),
                self::standard('view', RiskLevel::LOW),
                self::standard('create', RiskLevel::MEDIUM),
                self::standard('update', RiskLevel::MEDIUM),
                self::standard('delete', RiskLevel::HIGH),
            ],
            PermissionPreset::READ_ONLY => [
                self::standard('view_any', RiskLevel::LOW),
                self::standard('view', RiskLevel::LOW),
            ],
            PermissionPreset::NONE => [],
        };

        $keyed = [];
        foreach ($actions as $action) {
            $keyed[$action->key] = $action;
        }

        return $keyed;
    }

    private static function standard(string $key, RiskLevel $risk): PermissionAction
    {
        return PermissionAction::make($key)->risk($risk);
    }

    private static function withDefaults(PermissionAction $action): PermissionAction
    {
        return $action->riskLevel === null ? $action->risk(RiskLevel::MEDIUM) : $action;
    }

    private static function merge(PermissionAction $base, PermissionAction $override): PermissionAction
    {
        $merged = $base;
        if ($override->hasLabel) {
            $merged = $merged->label((string) $override->actionLabel);
        }
        if ($override->hasDescription) {
            $merged = $merged->description($override->actionDescription);
        }
        if ($override->riskLevel !== null) {
            $merged = $merged->risk($override->riskLevel);
        }
        if ($override->isDirectGrantable !== null) {
            $merged = $merged->directGrantable($override->isDirectGrantable);
        }
        foreach ($override->recommendations as $recommendation) {
            $merged = $merged->recommend($recommendation);
        }

        return $merged;
    }

    private static function assertKey(string $key, string $kind, string $class): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidResourceDefinition("Resource [{$class}] has invalid {$kind} key [{$key}]. Keys must match ^[a-z][a-z0-9_]*$ and cannot contain dots.");
        }
    }

    /** @return array<int, mixed> */
    private static function rawActions(): array
    {
        return static::actions();
    }

    /** @return array<int, mixed> */
    private static function rawExclusions(): array
    {
        return static::exceptActions();
    }
}
