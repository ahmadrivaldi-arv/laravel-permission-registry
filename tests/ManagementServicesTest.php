<?php

declare(strict_types=1);

use Ahmdrv\PermissionRegistry\Contracts\ManagementAuthorizer;
use Ahmdrv\PermissionRegistry\Events\DirectPermissionGranted;
use Ahmdrv\PermissionRegistry\Events\DirectPermissionRevoked;
use Ahmdrv\PermissionRegistry\Events\RolePermissionsSynchronized;
use Ahmdrv\PermissionRegistry\Events\UserRolesSynchronized;
use Ahmdrv\PermissionRegistry\Exceptions\DirectPermissionsDisabled;
use Ahmdrv\PermissionRegistry\Exceptions\GuardMismatch;
use Ahmdrv\PermissionRegistry\Exceptions\PermissionNotDirectGrantable;
use Ahmdrv\PermissionRegistry\Exceptions\UnregisteredPermission;
use Ahmdrv\PermissionRegistry\Services\AccessInspector;
use Ahmdrv\PermissionRegistry\Services\DirectPermissionManager;
use Ahmdrv\PermissionRegistry\Services\PermissionSynchronizer;
use Ahmdrv\PermissionRegistry\Services\RoleManager;
use Ahmdrv\PermissionRegistry\Services\UserRoleManager;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\DenyAllAuthorizer;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ProductPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ReportPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\TestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    config()->set('permission-registry.resources', [ProductPermissionResource::class, ReportPermissionResource::class]);
    config()->set('permission-registry.discovery.enabled', false);
    app(PermissionSynchronizer::class)->sync();
    $this->actor = TestUser::create(['name' => 'Administrator']);
    $this->user = TestUser::create(['name' => 'Managed User']);
});

it('synchronizes only explicitly requested registered role permissions', function () {
    Event::fake([RolePermissionsSynchronized::class]);
    $manager = app(RoleManager::class);
    $role = $manager->create($this->actor, 'publisher');
    $manager->syncPermissions($this->actor, $role, ['products.publish']);

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['products.publish']);
    Event::assertDispatched(RolePermissionsSynchronized::class);
    Event::assertDispatchedTimes(RolePermissionsSynchronized::class, 1);

    $manager->syncPermissions($this->actor, $role->fresh(), ['products.publish']);
    Event::assertDispatchedTimes(RolePermissionsSynchronized::class, 1);
});

it('rejects unregistered permissions and guard mismatches before role mutation', function () {
    $manager = app(RoleManager::class);
    $role = $manager->create($this->actor, 'publisher');

    expect(fn () => $manager->syncPermissions($this->actor, $role, ['products.typo']))
        ->toThrow(UnregisteredPermission::class)
        ->and(fn () => $manager->syncPermissions($this->actor, Role::findOrCreate('api-role', 'api'), ['products.view']))
        ->toThrow(GuardMismatch::class);

    expect($role->fresh()->permissions)->toHaveCount(0);
});

it('assigns removes and synchronizes user roles with post-success events', function () {
    Event::fake([UserRolesSynchronized::class]);
    $roles = app(UserRoleManager::class);
    Role::findOrCreate('editor', 'web');
    Role::findOrCreate('auditor', 'web');

    $roles->sync($this->actor, $this->user, ['editor', 'auditor']);
    expect($this->user->fresh()->roles->pluck('name')->sort()->values()->all())->toBe(['auditor', 'editor']);

    $roles->remove($this->actor, $this->user->fresh(), 'editor');
    expect($this->user->fresh()->roles->pluck('name')->all())->toBe(['auditor']);
    Event::assertDispatchedTimes(UserRolesSynchronized::class, 2);
});

it('keeps direct permissions opt-in and restricted per definition', function () {
    $manager = app(DirectPermissionManager::class);

    expect(fn () => $manager->grant($this->actor, $this->user, 'products.publish'))
        ->toThrow(DirectPermissionsDisabled::class);

    config()->set('permission-registry.direct_permissions.enabled', true);
    expect(fn () => $manager->grant($this->actor, $this->user, 'products.view'))
        ->toThrow(PermissionNotDirectGrantable::class);
    expect($this->user->fresh()->getDirectPermissions())->toHaveCount(0);
});

it('grants and revokes only the named direct permission without recommendation expansion', function () {
    Event::fake([DirectPermissionGranted::class, DirectPermissionRevoked::class]);
    config()->set('permission-registry.direct_permissions.enabled', true);
    $manager = app(DirectPermissionManager::class);
    $manager->grant($this->actor, $this->user, 'products.publish');

    expect($this->user->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['products.publish'])
        ->and($this->user->fresh()->can('reports.view_any'))->toBeFalse();
    Event::assertDispatched(DirectPermissionGranted::class);

    $manager->revoke($this->actor, $this->user->fresh(), 'products.publish');
    expect($this->user->fresh()->getDirectPermissions())->toHaveCount(0);
    Event::assertDispatched(DirectPermissionRevoked::class);
});

it('revoking a direct permission preserves access inherited through a role', function () {
    config()->set('permission-registry.direct_permissions.enabled', true);
    $role = Role::findOrCreate('publisher', 'web');
    $role->givePermissionTo('products.publish');
    $this->user->assignRole($role);
    $manager = app(DirectPermissionManager::class);
    $manager->grant($this->actor, $this->user, 'products.publish');
    $manager->revoke($this->actor, $this->user->fresh(), 'products.publish');

    expect($this->user->fresh()->can('products.publish'))->toBeTrue()
        ->and($this->user->fresh()->getDirectPermissions())->toHaveCount(0);
});

it('inspects effective permission sources', function () {
    config()->set('permission-registry.direct_permissions.enabled', true);
    $role = Role::findOrCreate('viewer', 'web');
    $role->givePermissionTo('reports.view');
    $this->user->assignRole($role);
    app(DirectPermissionManager::class)->grant($this->actor, $this->user, 'products.publish');

    $access = collect(app(AccessInspector::class)->inspect($this->user->fresh()))->keyBy('permission');
    expect($access['products.publish']->direct)->toBeTrue()
        ->and($access['products.publish']->roles)->toBe([])
        ->and($access['reports.view']->direct)->toBeFalse()
        ->and($access['reports.view']->roles)->toBe(['viewer']);
});

it('denies management before any database mutation or event', function () {
    Event::fake([RolePermissionsSynchronized::class, UserRolesSynchronized::class]);
    app()->bind(ManagementAuthorizer::class, DenyAllAuthorizer::class);
    app()->forgetInstance(RoleManager::class);

    expect(fn () => app(RoleManager::class)->create($this->actor, 'forbidden'))
        ->toThrow(AuthorizationException::class);
    expect(Role::where('name', 'forbidden')->exists())->toBeFalse();
    Event::assertNotDispatched(RolePermissionsSynchronized::class);
});

it('clears Spatie permission cache after successful synchronization', function () {
    $registrar = app(PermissionRegistrar::class);
    $registrar->getPermissions();
    Permission::where('name', 'products.delete')->delete();

    app(PermissionSynchronizer::class)->sync();

    expect($registrar->getPermissions()->pluck('name'))->toContain('products.delete');
});
