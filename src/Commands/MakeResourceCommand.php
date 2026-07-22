<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Commands;

use Ahmdrv\PermissionRegistry\Enums\PermissionPreset;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeResourceCommand extends Command
{
    protected $signature = 'rbac:make-resource
        {name : Resource name, such as Product}
        {--key= : Explicit plural snake_case resource key}
        {--group=general : Organization group key}
        {--preset=crud : crud, read-only, or none}
        {--force : Overwrite an existing resource}';

    protected $description = 'Create a code-first permission resource';

    public function __construct(private readonly Filesystem $files, private readonly Repository $config)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $input = (string) $this->argument('name');
        $base = Str::studly($input);
        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $base)) {
            $this->components->error("Invalid class name [{$input}]. Use letters and numbers and begin with a letter.");

            return self::FAILURE;
        }
        $class = Str::endsWith($base, 'PermissionResource') ? $base : $base.'PermissionResource';
        $subject = Str::beforeLast($class, 'PermissionResource');
        $key = $this->option('key') !== null ? (string) $this->option('key') : Str::plural(Str::snake($subject));
        $group = (string) $this->option('group');
        if (! $this->validKey($key) || ! $this->validKey($group)) {
            $this->components->error('Resource key and group must match ^[a-z][a-z0-9_]*$ and cannot contain dots. Use --key for domains Laravel cannot pluralize correctly.');

            return self::FAILURE;
        }

        $preset = PermissionPreset::tryFrom((string) $this->option('preset'));
        if ($preset === null) {
            $this->components->error('Invalid preset. Supported values: crud, read-only, none.');

            return self::FAILURE;
        }

        $namespace = $this->config->get('permission-registry.discovery.namespace');
        $paths = $this->config->get('permission-registry.discovery.paths');
        if (! is_string($namespace) || ! is_array($paths) || ! isset($paths[0]) || ! is_string($paths[0])) {
            $this->components->error('Configure a discovery namespace and at least one discovery path before generating resources.');

            return self::FAILURE;
        }

        $path = rtrim($paths[0], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$class.'.php';
        if ($this->files->exists($path) && ! (bool) $this->option('force')) {
            $this->components->error("Resource [{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $stub = $this->files->get(__DIR__.'/../../resources/stubs/permission-resource.stub');
        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ preset }}', '{{ key }}', '{{ label }}', '{{ group }}'],
            [trim($namespace, '\\'), $class, $preset->name, $key, Str::headline($subject), $group],
            $stub,
        );
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
        $fqcn = trim($namespace, '\\').'\\'.$class;
        $this->components->info("Created [{$path}].");
        if (! (bool) $this->config->get('permission-registry.discovery.enabled', true)) {
            $this->components->warn("Discovery is disabled. Add [{$fqcn}::class] to permission-registry.resources.");
        }

        return self::SUCCESS;
    }

    private function validKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $key);
    }
}
