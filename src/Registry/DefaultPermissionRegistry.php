<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Registry;

use Ahmdrv\PermissionRegistry\Contracts\PermissionRegistry;
use Ahmdrv\PermissionRegistry\Definitions\PermissionDefinition;
use Ahmdrv\PermissionRegistry\Definitions\ResourceDefinition;
use Ahmdrv\PermissionRegistry\Exceptions\DuplicateDefinition;
use Ahmdrv\PermissionRegistry\Exceptions\RegistryValidationException;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Illuminate\Contracts\Config\Repository;
use ReflectionClass;
use Throwable;

final class DefaultPermissionRegistry implements PermissionRegistry
{
    /** @var array<string, true> */
    private array $registered = [];

    /** @var list<ResourceDefinition>|null */
    private ?array $resources = null;

    /** @var list<PermissionDefinition>|null */
    private ?array $permissions = null;

    /** @var list<string> */
    private array $validationWarnings = [];

    public function __construct(private readonly Repository $config) {}

    public function register(string $resourceClass): PermissionRegistry
    {
        $this->registered[$resourceClass] = true;
        $this->resources = null;
        $this->permissions = null;

        return $this;
    }

    public function registerMany(iterable $resourceClasses): PermissionRegistry
    {
        foreach ($resourceClasses as $resourceClass) {
            $this->register($resourceClass);
        }

        return $this;
    }

    public function resources(): array
    {
        $this->build();

        return $this->resources ?? [];
    }

    public function permissions(): array
    {
        $this->build();

        return $this->permissions ?? [];
    }

    public function recommendations(): array
    {
        $recommendations = [];
        foreach ($this->permissions() as $permission) {
            array_push($recommendations, ...$permission->recommendations);
        }

        return $recommendations;
    }

    public function resourcesByGroup(): array
    {
        $groups = [];
        foreach ($this->resources() as $resource) {
            $groups[$resource->groupKey][] = $resource;
        }
        ksort($groups);

        return $groups;
    }

    public function permissionsByGroup(): array
    {
        $groups = [];
        foreach ($this->permissions() as $permission) {
            $groups[$permission->groupKey][] = $permission;
        }
        ksort($groups);

        return $groups;
    }

    public function findResource(string $key): ?ResourceDefinition
    {
        foreach ($this->resources() as $resource) {
            if ($resource->key === $key) {
                return $resource;
            }
        }

        return null;
    }

    public function findPermission(string $name): ?PermissionDefinition
    {
        foreach ($this->permissions() as $permission) {
            if ($permission->name === $name) {
                return $permission;
            }
        }

        return null;
    }

    public function hasPermission(string $name): bool
    {
        return $this->findPermission($name) !== null;
    }

    public function warnings(): array
    {
        $this->build();

        return $this->validationWarnings;
    }

    public function validate(): void
    {
        $this->build();
    }

    private function build(): void
    {
        if ($this->resources !== null) {
            return;
        }

        $errors = [];
        $classes = array_keys($this->registered);
        $configured = $this->config->get('permission-registry.resources', []);
        if (! is_array($configured)) {
            $errors[] = 'Configuration [permission-registry.resources] must be an array of PermissionResource class names.';
        } else {
            foreach ($configured as $index => $class) {
                if (! is_string($class)) {
                    $errors[] = "Configured resource at index [{$index}] must be a class-string.";

                    continue;
                }
                $classes[] = $class;
            }
        }

        try {
            array_push($classes, ...$this->discover());
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);
        $resources = [];
        $resourceKeys = [];
        $permissionNames = [];

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                $errors[] = "Registered permission resource class [{$class}] does not exist or cannot be autoloaded.";

                continue;
            }
            if (! is_subclass_of($class, PermissionResource::class)) {
                $errors[] = "Registered class [{$class}] must extend ".PermissionResource::class.'.';

                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                $errors[] = "Registered permission resource [{$class}] must be concrete, not abstract.";

                continue;
            }

            try {
                $definition = $class::definition();
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            if (isset($resourceKeys[$definition->key])) {
                $errors[] = (new DuplicateDefinition("Duplicate resource key [{$definition->key}] declared by [{$resourceKeys[$definition->key]}] and [{$class}]."))->getMessage();
            } else {
                $resourceKeys[$definition->key] = $class;
            }

            foreach ($definition->actions as $permission) {
                if (isset($permissionNames[$permission->name])) {
                    $errors[] = (new DuplicateDefinition("Duplicate permission [{$permission->name}] declared by [{$permissionNames[$permission->name]}] and [{$class}]."))->getMessage();
                } else {
                    $permissionNames[$permission->name] = $class;
                }
            }
            $resources[] = $definition;
        }

        usort($resources, static fn (ResourceDefinition $a, ResourceDefinition $b): int => [$a->groupKey, $a->key] <=> [$b->groupKey, $b->key]);
        $permissions = [];
        foreach ($resources as $resource) {
            array_push($permissions, ...$resource->actions);
        }

        $this->validateRecommendations($permissions, $errors);

        if ($errors !== []) {
            throw new RegistryValidationException(array_values(array_unique($errors)));
        }

        $this->resources = $resources;
        $this->permissions = $permissions;
    }

    /** @return list<class-string> */
    private function discover(): array
    {
        if (! (bool) $this->config->get('permission-registry.discovery.enabled', true)) {
            return [];
        }

        $namespace = $this->config->get('permission-registry.discovery.namespace', 'App\\Authorization\\Permissions');
        $paths = $this->config->get('permission-registry.discovery.paths', []);
        if (! is_string($namespace) || trim($namespace, '\\') === '') {
            throw new RegistryValidationException(['Discovery namespace must be a non-empty string.']);
        }
        if (! is_array($paths) || $paths === []) {
            throw new RegistryValidationException(['Discovery paths must be a non-empty array when discovery is enabled.']);
        }

        $classes = [];
        foreach ($paths as $path) {
            if (! is_string($path) || ! is_dir($path) || ! is_readable($path)) {
                throw new RegistryValidationException(['Discovery path ['.get_debug_type($path).":{$path}] is not a readable directory. Create it or disable discovery."]);
            }
            $files = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
            sort($files, SORT_STRING);

            foreach ($files as $file) {
                $relative = substr($file, strlen(rtrim($path, DIRECTORY_SEPARATOR)) + 1, -4);
                $class = trim($namespace, '\\').'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
                if (! str_ends_with($file, 'PermissionResource.php') && ! class_exists($class, false)) {
                    continue;
                }
                if (! class_exists($class, false) && ! $this->declaresResourceSubclass($file)) {
                    continue;
                }
                if (! class_exists($class, false)) {
                    require_once $file;
                }
                if (! class_exists($class)) {
                    throw new RegistryValidationException(["Discovery candidate [{$file}] did not declare expected class [{$class}]. Check the configured path and namespace mapping."]);
                }
                if (is_subclass_of($class, PermissionResource::class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    private function declaresResourceSubclass(string $file): bool
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return false;
        }

        $afterExtends = false;
        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && $token[0] === T_EXTENDS) {
                $afterExtends = true;

                continue;
            }
            if (! $afterExtends || ! is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                return str_ends_with($token[1], 'PermissionResource');
            }
        }

        return false;
    }

    /**
     * @param  list<PermissionDefinition>  $permissions
     * @param  list<string>  $errors
     */
    private function validateRecommendations(array $permissions, array &$errors): void
    {
        $names = array_fill_keys(array_map(static fn (PermissionDefinition $permission): string => $permission->name, $permissions), true);
        $graph = [];

        foreach ($permissions as $permission) {
            $seen = [];
            foreach ($permission->recommendations as $recommendation) {
                $target = $recommendation->targetPermission;
                if (! preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $target)) {
                    $errors[] = "Permission [{$permission->name}] recommends invalid target [{$target}]. Use a fully qualified registered permission name.";
                } elseif ($target === $permission->name) {
                    $errors[] = "Permission [{$permission->name}] cannot recommend itself.";
                } elseif (isset($seen[$target])) {
                    $errors[] = "Permission [{$permission->name}] recommends [{$target}] more than once.";
                } elseif (! isset($names[$target])) {
                    $errors[] = "Permission [{$permission->name}] recommends missing target [{$target}]. Register that permission or remove the recommendation.";
                } else {
                    $graph[$permission->name][] = $target;
                }
                $seen[$target] = true;
            }
        }

        $this->validationWarnings = $this->cycleWarnings($graph);
    }

    /** @param array<string, list<string>> $graph
     * @return list<string>
     */
    private function cycleWarnings(array $graph): array
    {
        $warnings = [];
        $visited = [];
        $active = [];
        $stack = [];

        $visit = function (string $node) use (&$visit, &$warnings, &$visited, &$active, &$stack, $graph): void {
            $visited[$node] = true;
            $active[$node] = count($stack);
            $stack[] = $node;
            foreach ($graph[$node] ?? [] as $target) {
                if (! isset($visited[$target])) {
                    $visit($target);
                } elseif (isset($active[$target])) {
                    $cycle = array_slice($stack, $active[$target]);
                    $cycle[] = $target;
                    $warnings[] = 'Advisory recommendation cycle detected: '.implode(' -> ', $cycle).'. This does not affect authorization.';
                }
            }
            array_pop($stack);
            unset($active[$node]);
        };

        foreach (array_keys($graph) as $node) {
            if (! isset($visited[$node])) {
                $visit($node);
            }
        }

        sort($warnings, SORT_STRING);

        return array_values(array_unique($warnings));
    }
}
