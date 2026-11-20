<?php

declare(strict_types=1);

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ProductPermissionResource;
use Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources\ReportPermissionResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config()->set('permission-registry.resources', [ProductPermissionResource::class, ReportPermissionResource::class]);
    config()->set('permission-registry.discovery.enabled', false);
});

it('validates and lists definitions including recommendations', function () {
    $this->artisan('rbac:validate')
        ->expectsOutputToContain('2 resources and 8 permissions are valid')
        ->assertSuccessful();

    foreach (['products.publish', 'reports.view_any', 'Useful for report history'] as $expectedOutput) {
        $this->artisan('rbac:list', ['--resource' => 'products', '--with-recommendations' => true, '--json' => true])
            ->expectsOutputToContain($expectedOutput)
            ->assertSuccessful();
    }

    $this->artisan('rbac:list', ['--risk' => 'invalid'])->assertFailed();
});

it('generates a strict resource and honors force and discovery-disabled guidance', function () {
    $directory = sys_get_temp_dir().'/permission-registry-generator-'.bin2hex(random_bytes(5));
    config()->set('permission-registry.discovery.paths', [$directory]);
    config()->set('permission-registry.discovery.namespace', 'App\\Authorization\\Permissions');

    $this->artisan('rbac:make-resource', [
        'name' => 'Product', '--key' => 'catalog_products', '--group' => 'catalog', '--preset' => 'read-only',
    ])->expectsOutputToContain('Discovery is disabled')->assertSuccessful();

    $path = $directory.'/ProductPermissionResource.php';
    expect(File::get($path))->toContain(
        'declare(strict_types=1);',
        'namespace App\\Authorization\\Permissions;',
        'PermissionPreset::READ_ONLY',
        "return 'catalog_products';",
        "return 'catalog';",
    )->not->toContain('function actions');

    $this->artisan('rbac:make-resource', ['name' => 'Product'])->assertFailed();
    $this->artisan('rbac:make-resource', ['name' => 'Product', '--force' => true])->assertSuccessful();
});

it('discovers only resource classes from a configured path', function () {
    $directory = sys_get_temp_dir().'/permission-registry-discovery-'.bin2hex(random_bytes(5));
    File::ensureDirectoryExists($directory);
    File::put($directory.'/AuditPermissionResource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Discovery\Permissions;
use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
final class AuditPermissionResource extends PermissionResource
{
    protected static PermissionPreset $preset = PermissionPreset::READ_ONLY;
    public static function key(): string { return 'audits'; }
    public static function label(): string { return 'Audits'; }
    public static function group(): string { return 'security'; }
}
PHP);
    File::put($directory.'/Unrelated.php', '<?php namespace Discovery\\Permissions; final class Unrelated {}');
    config()->set('permission-registry.resources', []);
    config()->set('permission-registry.discovery.enabled', true);
    config()->set('permission-registry.discovery.paths', [$directory]);
    config()->set('permission-registry.discovery.namespace', 'Discovery\\Permissions');
    app()->forgetInstance(PermissionRegistry::class);

    expect(app(PermissionRegistry::class)->findResource('audits'))->not->toBeNull()
        ->and(app(PermissionRegistry::class)->resources())->toHaveCount(1)
        ->and(class_exists('Discovery\\Permissions\\Unrelated', false))->toBeFalse();
});

it('reports deterministic diff categories', function () {
    Permission::findOrCreate('products.view_any', 'web');
    Permission::findOrCreate('products.legacy', 'web');
    Permission::findOrCreate('third_party.use', 'web');
    Permission::findOrCreate('plain_permission', 'web');

    $exit = Artisan::call('rbac:diff', ['--json' => true]);
    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('managed_orphans', 'products.legacy', 'unmanaged', 'third_party.use');
});

it('synchronizes additively idempotently and ignores recommendations', function () {
    Permission::findOrCreate('third_party.use', 'web');

    $this->artisan('rbac:sync')->expectsOutputToContain('8 created')->assertSuccessful();
    $this->artisan('rbac:sync')->expectsOutputToContain('0 created, 8 existing')->assertSuccessful();

    expect(Permission::where('guard_name', 'web')->pluck('name')->all())
        ->toContain('third_party.use', 'products.publish', 'reports.view_any')
        ->toHaveCount(9);
});

it('supports dry-run without writes', function () {
    $this->artisan('rbac:sync', ['--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();

    expect(Permission::count())->toBe(0);
});

it('prunes only displayed managed orphans and never unmanaged permissions', function () {
    $this->artisan('rbac:sync')->assertSuccessful();
    Permission::where('name', 'products.delete')->delete();
    Permission::findOrCreate('products.legacy', 'web');
    Permission::findOrCreate('removed_resource.view', 'web');
    Permission::findOrCreate('third_party.use', 'web');

    $this->artisan('rbac:sync', ['--prune' => true])
        ->expectsOutputToContain('products.legacy')
        ->expectsConfirmation('Delete exactly these managed orphan permissions?', 'yes')
        ->assertSuccessful();

    expect(Permission::where('name', 'products.legacy')->exists())->toBeFalse()
        ->and(Permission::where('name', 'removed_resource.view')->exists())->toBeTrue()
        ->and(Permission::where('name', 'third_party.use')->exists())->toBeTrue()
        ->and(Permission::where('name', 'products.delete')->exists())->toBeTrue();
});

it('renders a structured validation summary and itemized failures', function () {
    $this->artisan('rbac:validate')
        ->expectsOutputToContain('Permission registry')
        ->expectsOutputToContain('Recommendations')
        ->expectsOutputToContain('Warnings')
        ->assertSuccessful();

    config()->set('permission-registry.resources', [ProductPermissionResource::class, ReportPermissionResource::class, 'Missing\\PermissionResource']);
    app()->forgetInstance(PermissionRegistry::class);

    $this->artisan('rbac:validate')
        ->expectsOutputToContain('Permission registry is invalid: 1 error found')
        ->expectsOutputToContain('Missing\\PermissionResource')
        ->assertFailed();
});

it('lists permissions grouped by group and resource with risk and direct eligibility', function () {
    $this->artisan('rbac:list', ['--with-recommendations' => true])
        ->expectsOutputToContain('CATALOG')
        ->expectsOutputToContain('products · crud · 6 permissions')
        ->expectsOutputToContain('products.publish Publish product')
        ->expectsOutputToContain('recommends reports.view_any')
        ->expectsOutputToContain('Showing 8 permissions across 2 resources in 2 groups.')
        ->assertSuccessful();

    $this->artisan('rbac:list', ['--group' => 'missing_group'])
        ->expectsOutputToContain('No permissions match the filters: group=missing_group.')
        ->assertSuccessful();
});

it('renders diff sections with next steps and a matching state', function () {
    Permission::findOrCreate('products.legacy', 'web');
    Permission::findOrCreate('third_party.use', 'web');

    $this->artisan('rbac:diff')
        ->expectsOutputToContain('Permission diff guard [web]')
        ->expectsOutputToContain('+ products.publish')
        ->expectsOutputToContain('− products.legacy')
        ->expectsOutputToContain('· third_party.use')
        ->expectsOutputToContain('php artisan rbac:sync --prune')
        ->assertSuccessful();

    Permission::where('name', 'products.legacy')->delete();
    $this->artisan('rbac:sync')->assertSuccessful();

    $this->artisan('rbac:diff')
        ->expectsOutputToContain('Run with -v to list synchronized permissions.')
        ->expectsOutputToContain('The database matches the registry for guard [web].')
        ->assertSuccessful();

    expect(Permission::where('name', 'third_party.use')->exists())->toBeTrue();
});

it('itemizes sync changes and distinguishes dry runs and no-ops', function () {
    $this->artisan('rbac:sync', ['--dry-run' => true])
        ->expectsOutputToContain('To create (8)')
        ->expectsOutputToContain('8 to create, 0 existing, 0 to delete, 0 untouched. No changes were written.')
        ->assertSuccessful();
    expect(Permission::count())->toBe(0);

    $this->artisan('rbac:sync')
        ->expectsOutputToContain('Created (8)')
        ->expectsOutputToContain('+ products.publish')
        ->assertSuccessful();

    Permission::findOrCreate('products.legacy', 'web');
    $this->artisan('rbac:sync')
        ->expectsOutputToContain('Nothing to synchronize for guard [web]')
        ->expectsOutputToContain('1 managed orphan kept.')
        ->assertSuccessful();

    $this->artisan('rbac:sync', ['--prune' => true])
        ->expectsOutputToContain('1 managed orphan permission will be deleted for guard [web]')
        ->expectsConfirmation('Delete exactly these managed orphan permissions?', 'no')
        ->expectsOutputToContain('Synchronization cancelled; no database changes were made.')
        ->assertFailed();
    expect(Permission::where('name', 'products.legacy')->exists())->toBeTrue();
});

it('summarizes generated resources with next steps', function () {
    config()->set('permission-registry.discovery.enabled', true);
    config()->set('permission-registry.discovery.paths', [sys_get_temp_dir().'/permission-registry-summary-'.bin2hex(random_bytes(5))]);
    config()->set('permission-registry.discovery.namespace', 'App\\Authorization\\Permissions');

    $this->artisan('rbac:make-resource', ['name' => 'Invoice', '--group' => 'billing'])
        ->expectsOutputToContain('Created permission resource')
        ->expectsOutputToContain('invoices')
        ->expectsOutputToContain('discovered automatically')
        ->expectsOutputToContain('php artisan rbac:validate')
        ->doesntExpectOutputToContain('Discovery is disabled')
        ->assertSuccessful();

    $this->artisan('rbac:make-resource', ['name' => 'Invoice', '--force' => true])
        ->expectsOutputToContain('Overwrote permission resource')
        ->assertSuccessful();
});
