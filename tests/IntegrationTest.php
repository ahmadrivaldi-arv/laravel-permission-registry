<?php

declare(strict_types=1);

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Registry\DefaultPermissionRegistry;
use Ahmdrv\PermissionRegistry\Services\AccessInspector;
use Ahmdrv\PermissionRegistry\Services\DirectPermissionManager;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Ahmdrv\PermissionRegistry\Services\RoleManager;
use Ahmdrv\PermissionRegistry\Services\UserRoleManager;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ProductPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ReportPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('registers singleton contracts services and all package commands', function () {
    expect(app(PermissionRegistry::class))->toBeInstanceOf(DefaultPermissionRegistry::class)
        ->and(app(PermissionRegistry::class))->toBe(app(PermissionRegistry::class))
        ->and(app(ManagementAuthorizer::class))->toBeInstanceOf(ManagementAuthorizer::class)
        ->and(app(RoleManager::class))->toBeInstanceOf(RoleManager::class)
        ->and(app(UserRoleManager::class))->toBeInstanceOf(UserRoleManager::class)
        ->and(app(DirectPermissionManager::class))->toBeInstanceOf(DirectPermissionManager::class)
        ->and(app(AccessInspector::class))->toBeInstanceOf(AccessInspector::class)
        ->and(array_keys(Artisan::all()))->toContain('rbac:make-resource', 'rbac:validate', 'rbac:list', 'rbac:diff', 'rbac:sync');
});

it('fails validation cleanly for unusable discovery configuration without writes', function () {
    config()->set('permission-registry.resources', []);
    config()->set('permission-registry.discovery.enabled', true);
    config()->set('permission-registry.discovery.paths', ['/path/that/does/not/exist']);
    config()->set('permission-registry.discovery.namespace', 'App\\Authorization\\Permissions');

    expect(Artisan::call('rbac:validate'))->toBe(1)
        ->and(Artisan::output())->toContain('not a readable directory');
});

it('resolves configured guards for synchronization', function () {
    config()->set('permission-registry.resources', [ReportPermissionResource::class]);
    config()->set('permission-registry.guard', 'api');

    $result = app(PermissionSynchronizer::class)->sync();

    expect($result->guard)->toBe('api')
        ->and(Permission::where('guard_name', 'api')->count())->toBe(2);
});

it('direct synchronization never expands advisory recommendations', function () {
    config()->set('permission-registry.resources', [ProductPermissionResource::class, ReportPermissionResource::class]);
    config()->set('permission-registry.direct_permissions.enabled', true);
    app(PermissionSynchronizer::class)->sync();
    $actor = TestUser::create(['name' => 'Actor']);
    $user = TestUser::create(['name' => 'User']);

    app(DirectPermissionManager::class)->sync($actor, $user, ['products.publish']);

    expect($user->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['products.publish'])
        ->and($user->fresh()->can('reports.view_any'))->toBeFalse();
});

it('role grant revoke and deletion remain explicit', function () {
    config()->set('permission-registry.resources', [ProductPermissionResource::class, ReportPermissionResource::class]);
    app(PermissionSynchronizer::class)->sync();
    $actor = TestUser::create(['name' => 'Actor']);
    $manager = app(RoleManager::class);
    $role = $manager->create($actor, 'temporary');

    $manager->grant($actor, $role, 'products.publish');
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['products.publish']);

    $manager->revoke($actor, $role->fresh(), 'products.publish');
    expect($role->fresh()->permissions)->toHaveCount(0);

    $manager->delete($actor, $role->fresh());
    expect(Role::where('name', 'temporary')->exists())->toBeFalse();
});
