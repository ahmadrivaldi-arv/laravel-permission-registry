<?php

declare(strict_types=1);

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Exceptions\InvalidResourceDefinition;
use Ahmdrv\PermissionRegistry\Exceptions\RegistryValidationException;
use Ahmdrv\PermissionRegistry\Registry\DefaultPermissionRegistry;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ProductPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ReportPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\SettingsPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\WithoutDeletePermissionResource;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionRecommendation;

function registryWith(array $classes = []): PermissionRegistry
{
    config()->set('permission-registry.resources', []);
    config()->set('permission-registry.discovery.enabled', false);
    $registry = app(DefaultPermissionRegistry::class);
    $registry->registerMany($classes);

    return $registry;
}

it('expands presets and requires no CRUD declarations', function () {
    $registry = registryWith([ProductPermissionResource::class, ReportPermissionResource::class, SettingsPermissionResource::class]);

    expect(array_column($registry->findResource('products')->actions, 'name'))->toBe([
        'products.view_any', 'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish',
    ])->and(array_column($registry->findResource('reports')->actions, 'name'))->toBe([
        'reports.view_any', 'reports.view',
    ])->and(array_column($registry->findResource('settings')->actions, 'name'))->toBe(['settings.manage']);
});

it('adds custom actions and selectively overrides standard metadata', function () {
    $registry = registryWith([ProductPermissionResource::class, ReportPermissionResource::class]);

    expect($registry->findPermission('products.publish'))
        ->label->toBe('Publish product')
        ->risk->toBe(RiskLevel::HIGH)
        ->directGrantable->toBeTrue()
        ->and($registry->findPermission('products.delete'))
        ->label->toBe('Delete')
        ->risk->toBe(RiskLevel::CRITICAL)
        ->directGrantable->toBeFalse();
});

it('applies exclusions last', function () {
    $definition = WithoutDeletePermissionResource::definition();

    expect(array_column($definition->actions, 'name'))->not->toContain('documents.delete')->toHaveCount(4);
});

it('orders resources deterministically by group and key', function () {
    $registry = registryWith([SettingsPermissionResource::class, ProductPermissionResource::class, ReportPermissionResource::class]);

    expect(array_column($registry->resources(), 'key'))->toBe(['reports', 'products', 'settings']);
});

it('normalizes and exposes advisory recommendations', function () {
    $registry = registryWith([ProductPermissionResource::class, ReportPermissionResource::class]);
    $recommendation = $registry->recommendations()[0];

    expect($recommendation->sourcePermission)->toBe('products.publish')
        ->and($recommendation->targetPermission)->toBe('reports.view_any')
        ->and($recommendation->reason)->toBe('Useful for report history.');
});

it('rejects missing duplicate and self recommendations', function (string $mode) {
    $class = match ($mode) {
        'missing' => new class extends PermissionResource
        {
            public static function key(): string
            {
                return 'missing_targets';
            }

            public static function label(): string
            {
                return 'Missing';
            }

            public static function group(): string
            {
                return 'tests';
            }

            protected static function actions(): array
            {
                return [PermissionAction::make('run')->recommend(PermissionRecommendation::make('unknown.view'))];
            }
        },
        'duplicate' => new class extends PermissionResource
        {
            public static function key(): string
            {
                return 'duplicate_targets';
            }

            public static function label(): string
            {
                return 'Duplicate';
            }

            public static function group(): string
            {
                return 'tests';
            }

            protected static function actions(): array
            {
                return [PermissionAction::make('run')->recommend(PermissionRecommendation::make('reports.view'))->recommend(PermissionRecommendation::make('reports.view'))];
            }
        },
        default => new class extends PermissionResource
        {
            public static function key(): string
            {
                return 'self_targets';
            }

            public static function label(): string
            {
                return 'Self';
            }

            public static function group(): string
            {
                return 'tests';
            }

            protected static function actions(): array
            {
                return [PermissionAction::make('run')->recommend(PermissionRecommendation::make('self_targets.run'))];
            }
        },
    };

    expect(fn () => registryWith([$class::class, ReportPermissionResource::class])->validate())
        ->toThrow(RegistryValidationException::class, $mode === 'self' ? 'cannot recommend itself' : $mode);
})->with(['missing', 'duplicate', 'self']);

it('reports recommendation cycles as warnings without failing', function () {
    $one = new class extends PermissionResource
    {
        public static function key(): string
        {
            return 'cycle_ones';
        }

        public static function label(): string
        {
            return 'One';
        }

        public static function group(): string
        {
            return 'tests';
        }

        protected static function actions(): array
        {
            return [PermissionAction::make('run')->recommend(PermissionRecommendation::make('cycle_twos.run'))];
        }
    };
    $two = new class extends PermissionResource
    {
        public static function key(): string
        {
            return 'cycle_twos';
        }

        public static function label(): string
        {
            return 'Two';
        }

        public static function group(): string
        {
            return 'tests';
        }

        protected static function actions(): array
        {
            return [PermissionAction::make('run')->recommend(PermissionRecommendation::make('cycle_ones.run'))];
        }
    };
    $registry = registryWith([$two::class, $one::class]);

    expect($registry->warnings())->toHaveCount(1)->and($registry->permissions())->toHaveCount(12);
});

it('rejects invalid keys malformed actions and unknown exclusions', function () {
    $invalidKey = new class extends PermissionResource
    {
        public static function key(): string
        {
            return 'Bad.Key';
        }

        public static function label(): string
        {
            return 'Bad';
        }

        public static function group(): string
        {
            return 'tests';
        }
    };
    $malformed = new class extends PermissionResource
    {
        public static function key(): string
        {
            return 'malformed';
        }

        public static function label(): string
        {
            return 'Malformed';
        }

        public static function group(): string
        {
            return 'tests';
        }

        protected static function actions(): array
        {
            return ['not-an-action'];
        }
    };
    $exclusion = new class extends PermissionResource
    {
        public static function key(): string
        {
            return 'excluded';
        }

        public static function label(): string
        {
            return 'Excluded';
        }

        public static function group(): string
        {
            return 'tests';
        }

        protected static function exceptActions(): array
        {
            return ['publsih'];
        }
    };

    expect(fn () => $invalidKey::definition())->toThrow(InvalidResourceDefinition::class, 'invalid resource key')
        ->and(fn () => $malformed::definition())->toThrow(InvalidResourceDefinition::class, 'must be a PermissionAction')
        ->and(fn () => $exclusion::definition())->toThrow(InvalidResourceDefinition::class, 'excludes unknown action');
});

it('rejects duplicate resource keys and invalid registered classes', function () {
    $duplicate = new class extends PermissionResource
    {
        public static function key(): string
        {
            return 'products';
        }

        public static function label(): string
        {
            return 'Other Products';
        }

        public static function group(): string
        {
            return 'tests';
        }
    };

    expect(fn () => registryWith([ProductPermissionResource::class, $duplicate::class])->validate())
        ->toThrow(RegistryValidationException::class, 'Duplicate resource key')
        ->and(fn () => registryWith([stdClass::class])->validate())
        ->toThrow(RegistryValidationException::class, 'must extend');
});

it('supports explicit config registration when discovery is disabled', function () {
    config()->set('permission-registry.resources', [ReportPermissionResource::class]);
    config()->set('permission-registry.discovery.enabled', false);
    $registry = new DefaultPermissionRegistry(config());

    expect($registry->findResource('reports'))->not->toBeNull();
});
